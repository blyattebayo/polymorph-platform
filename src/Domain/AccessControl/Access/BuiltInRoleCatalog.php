<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Access;

final class BuiltInRoleCatalog
{
    public const ROLE_SYSTEM_ADMIN = 'system.admin';

    public const ROLE_ACCESS_POLICY_MANAGER = 'access.policy_manager';

    public const ROLE_SCHEMA_READER = 'schema.reader';

    public const ROLE_SCHEMA_MANAGER = 'schema.manager';

    public const ROLE_RECORDS_EDITOR = 'records.editor';

    public const ROLE_RECORDS_ADMIN = 'records.admin';

    public const ROLE_MEDIA_EDITOR = 'media.editor';

    public const ROLE_MEDIA_ADMIN = 'media.admin';

    public const ROLE_USERS_MANAGER = 'users.manager';

    /**
     * @return array<int, array{code:string,name:string,description:string}>
     */
    public static function definitions(): array
    {
        return [
            [
                'code' => self::ROLE_SYSTEM_ADMIN,
                'name' => 'System Administrator',
                'description' => 'Global super-admin with wildcard access across the platform.',
            ],
            [
                'code' => self::ROLE_ACCESS_POLICY_MANAGER,
                'name' => 'Access Policy Manager',
                'description' => 'Manages ACL policies and subject assignments.',
            ],
            [
                'code' => self::ROLE_SCHEMA_READER,
                'name' => 'Schema Reader',
                'description' => 'Read-only access to schema fields and schema-driven metadata.',
            ],
            [
                'code' => self::ROLE_SCHEMA_MANAGER,
                'name' => 'Schema Manager',
                'description' => 'Manages schemas and record definition structure.',
            ],
            [
                'code' => self::ROLE_RECORDS_EDITOR,
                'name' => 'Records Editor',
                'description' => 'Reads and updates records without delete permissions.',
            ],
            [
                'code' => self::ROLE_RECORDS_ADMIN,
                'name' => 'Records Administrator',
                'description' => 'Full records lifecycle access including delete.',
            ],
            [
                'code' => self::ROLE_MEDIA_EDITOR,
                'name' => 'Media Editor',
                'description' => 'Reads and updates media without delete permissions.',
            ],
            [
                'code' => self::ROLE_MEDIA_ADMIN,
                'name' => 'Media Administrator',
                'description' => 'Full media lifecycle access including delete.',
            ],
            [
                'code' => self::ROLE_USERS_MANAGER,
                'name' => 'Users Manager',
                'description' => 'Reads users and manages user lifecycle operations.',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function protectedRoleCodes(): array
    {
        return array_values(array_map(
            static fn (array $definition): string => (string) $definition['code'],
            self::definitions(),
        ));
    }

    /**
     * Единственная точка ответа на «эта роль встроенная?» — правило раньше
     * дублировалось inline-проверками в репозитории и HTTP-ресурсе.
     */
    public static function isProtected(string $roleCode): bool
    {
        return in_array($roleCode, self::protectedRoleCodes(), true);
    }

    /**
     * @return list<string>
     */
    public static function baselineRoleCodes(): array
    {
        return [
            self::ROLE_RECORDS_EDITOR,
            self::ROLE_MEDIA_EDITOR,
            self::ROLE_SCHEMA_READER,
        ];
    }
}
