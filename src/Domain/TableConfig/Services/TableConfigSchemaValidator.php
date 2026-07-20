<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\TableConfig\Services;

final class TableConfigSchemaValidator
{
    public function validateDefinitionV1(array $config): ?string
    {
        return $this->validateCommonV1($config);
    }

    public function validateOverrideV1(array $config): ?string
    {
        return $this->validateCommonV1($config);
    }

    private function validateCommonV1(array $config): ?string
    {
        if (!$this->isAssociative($config) && $config !== []) {
            return 'config_json must be an object.';
        }

        if (!array_key_exists('columns', $config)) {
            return null;
        }

        if (!is_array($config['columns'])) {
            return 'config_json.columns must be an object keyed by column key or an array of columns.';
        }

        if ($this->isAssociative($config['columns'])) {
            return $this->validateColumnsByKey($config['columns']);
        }

        return $this->validateColumnsList($config['columns']);
    }

    /**
     * @param array<string, mixed> $columns
     */
    private function validateColumnsByKey(array $columns): ?string
    {
        foreach ($columns as $columnKey => $columnConfig) {
            if (!is_array($columnConfig) || (!$this->isAssociative($columnConfig) && $columnConfig !== [])) {
                return sprintf('config_json.columns.%s must be an object.', (string) $columnKey);
            }

            $validationError = $this->validateColumnConfig($columnConfig, (string) $columnKey);
            if ($validationError !== null) {
                return $validationError;
            }
        }

        return null;
    }

    /**
     * @param array<int, mixed> $columns
     */
    private function validateColumnsList(array $columns): ?string
    {
        foreach ($columns as $index => $column) {
            if (!is_array($column) || (!$this->isAssociative($column) && $column !== [])) {
                return sprintf('config_json.columns.%d must be an object.', $index);
            }

            if (!array_key_exists('id', $column) || !is_string($column['id']) || trim($column['id']) === '') {
                return sprintf('config_json.columns.%d.id must be a non-empty string.', $index);
            }

            if (array_key_exists('layout', $column)) {
                if (!is_array($column['layout']) || (!$this->isAssociative($column['layout']) && $column['layout'] !== [])) {
                    return sprintf('config_json.columns.%d.layout must be an object.', $index);
                }

                $validationError = $this->validateRuntimeLayout($column['layout'], $index);
                if ($validationError !== null) {
                    return $validationError;
                }
            }

            if (array_key_exists('view', $column)) {
                if (!is_array($column['view']) || (!$this->isAssociative($column['view']) && $column['view'] !== [])) {
                    return sprintf('config_json.columns.%d.view must be an object.', $index);
                }

                if (array_key_exists('type', $column['view']) && !is_string($column['view']['type'])) {
                    return sprintf('config_json.columns.%d.view.type must be a string.', $index);
                }

                if (array_key_exists('props', $column['view'])) {
                    $props = $column['view']['props'];

                    if (!is_array($props) || (!$this->isAssociative($props) && $props !== [])) {
                        return sprintf('config_json.columns.%d.view.props must be an object.', $index);
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $columnConfig
     */
    private function validateColumnConfig(array $columnConfig, string $columnKey): ?string
    {
        if (array_key_exists('order', $columnConfig) && !is_int($columnConfig['order'])) {
            return sprintf('config_json.columns.%s.order must be an integer.', $columnKey);
        }

        if (array_key_exists('visible', $columnConfig) && !is_bool($columnConfig['visible'])) {
            return sprintf('config_json.columns.%s.visible must be a boolean.', $columnKey);
        }

        if (array_key_exists('pin', $columnConfig) && !$this->isAllowedPin($columnConfig['pin'])) {
            return sprintf('config_json.columns.%s.pin must be left, right, none, false or null.', $columnKey);
        }

        if (array_key_exists('view', $columnConfig)) {
            if (!is_array($columnConfig['view']) || (!$this->isAssociative($columnConfig['view']) && $columnConfig['view'] !== [])) {
                return sprintf('config_json.columns.%s.view must be an object.', $columnKey);
            }

            if (array_key_exists('props', $columnConfig['view'])) {
                $props = $columnConfig['view']['props'];

                if (!is_array($props) || (!$this->isAssociative($props) && $props !== [])) {
                    return sprintf('config_json.columns.%s.view.props must be an object.', $columnKey);
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $layout
     */
    private function validateRuntimeLayout(array $layout, int $index): ?string
    {
        if (array_key_exists('order', $layout) && !is_int($layout['order'])) {
            return sprintf('config_json.columns.%d.layout.order must be an integer.', $index);
        }

        if (array_key_exists('visible', $layout) && !is_bool($layout['visible'])) {
            return sprintf('config_json.columns.%d.layout.visible must be a boolean.', $index);
        }

        if (array_key_exists('pin', $layout) && !$this->isAllowedPin($layout['pin'])) {
            return sprintf('config_json.columns.%d.layout.pin must be left, right, none, false or null.', $index);
        }

        if (array_key_exists('width', $layout) && !is_int($layout['width'])) {
            return sprintf('config_json.columns.%d.layout.width must be an integer.', $index);
        }

        if (array_key_exists('minWidth', $layout) && !is_int($layout['minWidth'])) {
            return sprintf('config_json.columns.%d.layout.minWidth must be an integer.', $index);
        }

        if (array_key_exists('resizable', $layout) && !is_bool($layout['resizable'])) {
            return sprintf('config_json.columns.%d.layout.resizable must be a boolean.', $index);
        }

        return null;
    }

    private function isAllowedPin(mixed $pin): bool
    {
        return in_array($pin, ['left', 'right', 'none', null, false], true);
    }

    private function isAssociative(array $value): bool
    {
        if ($value === []) {
            return true;
        }

        return array_keys($value) !== range(0, count($value) - 1);
    }
}
