<?php

namespace Informika\QueryConstructor\Mapping;

use Doctrine\Common\Annotations\Reader as AnnotationReader;
use Informika\QueryConstructor\Mapping\Annotation\Entity;
use Informika\QueryConstructor\Mapping\Annotation\Property;

/**
 * @author Nikita Pushkov
 */
class Reader
{
    /**
     * @var AnnotationReader
     */
    protected $reader;

    /**
     * Constructor
     *
     * @param AnnotationReader $reader
     */
    public function __construct(AnnotationReader $reader)
    {
        $this->reader = $reader;
    }

    /**
     * @param string $className
     * @return ClassMetadata|null
     */
    public function getClassMetaData($className)
    {
        $reflection = new \ReflectionClass($className);
        $entityMetadata = $this->reader->getClassAnnotation(
            $reflection,
            Entity::CLASSNAME
        );
        if (!$entityMetadata) {
            return null;
        }
        $classMetadata = new ClassMetadata($entityMetadata);
        $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC | \ReflectionProperty::IS_PROTECTED | \ReflectionProperty::IS_PRIVATE);
        $aggreagatbleProperties = $this->filterOnly($properties, $entityMetadata->getSelect());
        $aggreagatbleProperties = $this->filterExcept($aggreagatbleProperties, $entityMetadata->getSelectExcept());
        $classMetadata->setAggregatableProperties($this->fetchProperties($aggreagatbleProperties));

        $classMetadata->setProperties($this->fetchProperties($properties));

        return $classMetadata;
    }

    /**
     * @param array $properties
     * @param array $names
     * @return array
     */
    protected function filterOnly(array $properties, array $names = null)
    {
        if ($names) {
            return array_filter($properties, function (\ReflectionProperty $property) use ($names) {
                return in_array($property->getName(), $names);
            });
        } else {
            return $properties;
        }
    }

    /**
     * @param array $properties
     * @param array $names
     * @return array
     */
    protected function filterExcept(array $properties, array $names = null)
    {
        if ($names) {
            return array_filter($properties, function (\ReflectionProperty $property) use ($names) {
                return !in_array($property->getName(), $names);
            });
        } else {
            return $properties;
        }
    }

    /**
     * @param array $properties
     * @return array
     */
    protected function fetchProperties(array $properties)
    {
        $result = [];
        foreach ($properties as $property) {
            $result[$property->getName()] = $this->makePropertyFromReflection($property);
        }

        return $result;
    }

    /**
     * @param \ReflectionProperty $property
     * @return Property
     */
    protected function makePropertyFromReflection(\ReflectionProperty $property)
    {
        $propertyMetadata = new Property();
        $propertyMetadata->title = ucfirst($property->getName());
        $phpdocPropertyType = $this->getPhpDocPropertyType($property);
        $propertyMetadata->type = $this->mapPropertyTypeFromPhpDoc($phpdocPropertyType);

        return $propertyMetadata;
    }

    /**
     * @param mixed $phpdocPropertyType
     * @return string
     */
    protected function mapPropertyTypeFromPhpDoc($phpdocPropertyType)
    {
        switch ($phpdocPropertyType) {
            case 'bool':
            case 'boolean':
            case 'int':
            case 'integer':
                return Property::TYPE_INTEGER;
            case 'DateTime':
            case '\DateTime':
                return Property::TYPE_DATE;
            default:
                return Property::TYPE_STRING;
        }
    }

    /**
     * Get type of property from property declaration
     *
     * @link http://stackoverflow.com/a/34340504
     *
     * @param \ReflectionProperty $property
     *
     * @return null|string
     */
    protected function getPhpDocPropertyType(\ReflectionProperty $property)
    {
        $doc = $property->getDocComment();
        preg_match_all('#@(.*?)\n#s', $doc, $annotations);
        if (isset($annotations[1])) {
            foreach ($annotations[1] as $annotation) {
                preg_match_all('/\s*(.*?)\s+(\S*)/s', $annotation, $parts);
                if (!isset($parts[1][0], $parts[2][0])) {
                    continue;
                }
                $declaration = $parts[1][0];
                $type = $parts[2][0];
                if ($declaration === 'var') {
                    if (substr($type, 0, 1) === '$') {
                        return null;
                    }
                    else {
                        return $type;
                    }
                }
            }
            return null;
        }
        return $doc;
    }
}
