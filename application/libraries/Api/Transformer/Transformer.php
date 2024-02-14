<?php

namespace LimeSurvey\Api\Transformer;

use LimeSurvey\Api\Transformer\Formatter\FormatterInterface;
use LimeSurvey\Api\Transformer\Registry\ValidationRegistry;

/**
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class Transformer implements TransformerInterface
{
    /** @var array */
    protected $dataMap = [];

    /** @var array */
    protected $defaultConfig = [];

    /** @var ?ValidationRegistry */
    public $registry;

    /**
     * Transform data
     *
     * Transforms data from one format to another.
     *
     * Default functionality is to map input data to output
     * data (or vice versa) using a data map.
     * Data map config also allows specification of type cast
     * and callable formatter.
     *
     * @param ?mixed $data
     * @param ?mixed $options
     * @return ?mixed
     * @throws TransformerException
     */
    public function transform($data, $options = [])
    {
        $data = $data ?? [];
        $options = $options ?? [];
        $dataMap = $this->getDataMap();
        $output = null;
        foreach ($dataMap as $key => $config) {
            if (!$config) {
                continue;
            }
            $config = $this->normaliseConfig(
                $config,
                $key,
                $options
            );
            $valueIsSet = array_key_exists($key, $data);
            $value = $data[$key] ?? null;
            // Null value reverts to default value
            // - the default value itself defaults to null
            if (is_null($value) && isset($config['default'])) {
                $value = $config['default'];
            }
            // Null value and null default
            // - skip if not required and if it wasn't set to null explicitly
            if (is_null($value) && !$config['required'] && !$valueIsSet) {
                continue;
            }
            $value = $this->cast($value, $config);
            $value = $this->format($value, $config);
            $errors = $this->validateKey(
                $key,
                $value,
                $config,
                $data,
                $options
            );

            if (is_array($errors)) {
                throw new TransformerException(print_r($errors, true));
            }

            if (
                $config['transformer'] instanceof TransformerInterface
                && isset($value)
            ) {
                $transformMethod = $config['collection'] ? 'transformAll' : 'transform';
                $value = $config['transformer']->{$transformMethod}(
                    $value,
                    $options
                );
            }

            if (!isset($output)) {
                $output = [];
            }
            $output[$config['key']] = $value;
        }
        return $output;
    }

    /**
     * Normalise config
     *
     * @param bool|int|string|array $config
     * @param string|int $inputKey
     * @param ?array $options
     * @return array
     */
    private function normaliseConfig($config, $inputKey, $options = [])
    {
        $configTemp = ['key' => $inputKey];
        if (is_string($config) || is_int($config)) {
            // map to new key name
            $configTemp['key'] = $config;
        }
        $config = array_merge(
            $this->getDefaultConfig(),
            $configTemp,
            is_array($config) ? $config : []
        );

        if ($this->registry) {
            $config = $this->registry->normalizeConfig($config, $options);
        }
        $config['key'] = isset($config['key']) ? $config['key'] : $inputKey;
        $config['type'] = isset($config['type']) ? $config['type'] : null;
        $config['collection'] = isset($config['collection']) ? $config['collection'] : false;
        $config['transformer'] = isset($config['transformer']) ? $config['transformer'] : null;
        $config['formatter'] = isset($config['formatter']) ? $config['formatter'] : null;
        $config['default'] = isset($config['default']) ? $config['default'] : null;

        return $config;
    }

    /**
     * Cast Value
     *
     * @param mixed $value
     * @param array $config
     * @return mixed
     */
    private function cast($value, $config)
    {
        $type = $config['type'];
        if (!is_null($value) && !empty($type)) {
            if (is_callable($type)) {
                $value = $type($value);
            } elseif (is_string($type)) {
                settype($value, $type);
            }
        }
        return $value;
    }

    /**
     * Format Value
     *
     * @param mixed $value
     * @param array $config
     * @return mixed
     */
    private function format($value, $config)
    {
        if (
            isset($config['formatter'])
            && $config['formatter'] instanceof FormatterInterface
        ) {
            $value = $config['formatter']->format($value);
        }
        return $value;
    }

    /**
     * @param mixed $data
     * @param ?array $options
     * @return boolean|array Returns true on success or array of errors.
     */
    public function validate($data, $options = [])
    {
        $options = $options ?? [];
        $dataMap = $this->getDataMap();
        $errors = [];
        foreach ($dataMap as $key => $config) {
            if (!$config) {
                continue;
            }
            $config = $this->normaliseConfig(
                $config,
                $key,
                $options
            );
            $value = $data[$key] ?? null;
            // Null value reverts to default value
            // - the default value itself defaults to null
            if (is_null($value) && isset($config['default'])) {
                $value = $config['default'];
            }
            $value = $this->cast($value, $config);
            $value = $this->format($value, $config);

            $fieldErrors = $this->validateKey(
                $key,
                $value,
                $config,
                $data,
                $options
            );
            if (is_array($fieldErrors)) {
                $errors = array_merge($errors, $fieldErrors);
            }
        }
        return empty($errors) ?: $errors;
    }

    /**
     * Validate Value
     *
     * Returns boolean true or array of errors.
     *
     * @param string $key
     * @param mixed $value
     * @param array $config
     * @param array $data
     * @param ?array $options
     * @return boolean|array
     */
    private function validateKey($key, $value, $config, $data, $options = [])
    {
        $options = $options ?? [];
        $errors = [];
        foreach ($config as $validationKey => $validationConfig) {
            if ($this->registry) {
                $validator = $this->registry->get($validationKey);
                if ($validator) {
                    $result = $validator->validate(
                        $key,
                        $value,
                        $config,
                        $data,
                        $options
                    );
                    if (is_array($result)) {
                        $errors[$key][] = $result;
                    }
                }
            }
        }

        if (
            $config['transformer'] instanceof TransformerInterface
            && isset($value)
        ) {
            $validateMethod = $config['collection'] ? 'validateAll' : 'validate';
            $subFieldErrors = $config['transformer']->{$validateMethod}(
                $value,
                $options
            );
            if (is_array($subFieldErrors)) {
                $errors = array_merge($errors, $subFieldErrors);
            }
        }

        return empty($errors) ?: $errors;
    }

    /**
     * Transform collection
     *
     * @param array $collection
     * @param ?array $options
     * @return array
     */
    public function transformAll($collection, $options = [])
    {
        $options = $options ?? [];
        return array_map(
            function ($allData) use ($options) {
                return $this->transform($allData, $options);
            },
            $collection
        );
    }

    /**
     * Validate collection
     *
     * @param array $collection
     * @param ?array $options
     * @return bool|array Returns true on success or array of errors.
     */
    public function validateAll($collection, $options = [])
    {
        $options = $options ?? [];
        $result = array_reduce(
            $collection,
            function ($carry, $data) use ($options) {
                $carry = is_array($carry) ? $carry : [];
                $oneResult = $this->validate($data, $options);
                return is_array($oneResult)
                    ? array_merge($carry, $oneResult)
                    : $carry;
            },
            []
        );
        return empty($result) ?: $result;
    }

    /**
     * Get data map
     *
     * @return array
     */
    public function getDataMap()
    {
        return $this->dataMap ? $this->dataMap : [];
    }

    /**
     * Set data map
     *
     * Data map is an associative array.
     * Key is the key in the source data and value is either boolean true
     * - to include the data as is or a a string field name to map the value
     * - to in the output.
     *
     * @param array $dataMap
     * @return void
     */
    public function setDataMap(array $dataMap)
    {
        $this->dataMap = $dataMap;
    }

    /**
     * Set default config
     *
     * @param array $defaultConfig
     * @return void
     */
    public function setDefaultConfig(array $defaultConfig)
    {
        $this->defaultConfig = $defaultConfig;
    }

    /**
     * Get default config
     *
     * @return array
     */
    private function getDefaultConfig()
    {
        return array_merge(
            [
                'default' => null,
                'required' => false,
                'null' => true,
                'empty' => true
            ],
            $this->defaultConfig
        );
    }

    /**
     * @param ValidationRegistry $registry
     * @return void
     */
    public function setRegistry(ValidationRegistry $registry)
    {
        $this->registry = $registry;
    }
}
