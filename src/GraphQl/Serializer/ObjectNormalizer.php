<?php

/*
 * This file is part of the API Platform project.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ApiPlatform\GraphQl\Serializer;

use ApiPlatform\Metadata\IdentifiersExtractorInterface;
use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\Util\ClassInfoTrait;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Decorates the output with GraphQL metadata when appropriate, but otherwise just
 * passes through to the decorated normalizer.
 */
final class ObjectNormalizer implements NormalizerInterface
{
    use ClassInfoTrait;

    public const FORMAT = 'graphql';
    public const ITEM_RESOURCE_CLASS_KEY = '#itemResourceClass';
    public const ITEM_IDENTIFIERS_KEY = '#itemIdentifiers';

    public function __construct(private readonly NormalizerInterface $decorated, private readonly IriConverterInterface $iriConverter, private readonly IdentifiersExtractorInterface $identifiersExtractor)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return self::FORMAT === $format && $this->decorated->supportsNormalization($data, $format, $context);
    }

    public function getSupportedTypes($format): array
    {
        return self::FORMAT === $format ? $this->decorated->getSupportedTypes($format) : [];
    }

    /**
     * {@inheritdoc}
     *
     * @throws UnexpectedValueException
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): array
    {
        if (isset($context['api_resource'])) {
            $originalResource = $context['api_resource'];
            unset($context['api_resource']);
        }

        $data = $this->decorated->normalize($object, $format, $context);
        if (!\is_array($data)) {
            throw new UnexpectedValueException('Expected data to be an array.');
        }

        if (!isset($originalResource)) {
            return $data;
        }

        if (isset($data['id'])) {
            $data['_id'] = $data['id'];
            $data['id'] = $this->iriConverter->getIriFromResource($originalResource);
        }

        if (!($context['no_resolver_data'] ?? false)) {
            $data[self::ITEM_RESOURCE_CLASS_KEY] = $this->getObjectClass($originalResource);
            $data[self::ITEM_IDENTIFIERS_KEY] = $this->identifiersExtractor->getIdentifiersFromItem($originalResource);
        }

        return $data;
    }
}
