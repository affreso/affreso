<?php
declare(strict_types=1);

/** Thin PDO wrapper. Write "{p}" in SQL for the configured table prefix. */
final class DB
{
    private static ?PDO $pdo = null;
    public static string $prefix = 'af_';

    public static function connect(array $c): void
    {
        self::$prefix = $c['prefix'] ?? 'af_';
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'], (int)($c['port'] ?? 3306), $c['name']);
        self::$pdo = new PDO($dsn, $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $offset = (new DateTime())->format('P');
        self::$pdo->exec("SET time_zone = '" . $offset . "'");
    }

    public static function pdo(): PDO
    {
        if (!self::$pdo) {
            throw new RuntimeException('Database not connected');
        }
        return self::$pdo;
    }

    public static function sql(string $sql): string
    {
        return str_replace('{p}', self::$prefix, $sql);
    }

    public static function run(string $sql, array $params = []): PDOStatement
    {
        $st = self::pdo()->prepare(self::sql($sql));
        $st->execute(array_values($params) === $params ? $params : $params);
        return $st;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    public static function row(string $sql, array $params = []): ?array
    {
        $r = self::run($sql, $params)->fetch();
        return $r === false ? null : $r;
    }

    public static function val(string $sql, array $params = []): mixed
    {
        $v = self::run($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    public static function col(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll(PDO::FETCH_COLUMN);
    }

    private static function ident(string $name): string
    {
        if (!preg_match('/^[a-z_][a-z0-9_]*$/', $name)) {
            throw new InvalidArgumentException('Bad identifier');
        }
        return '`' . $name . '`';
    }

    public static function insert(string $table, array $data): int
    {
        $cols = array_map([self::class, 'ident'], array_keys($data));
        $sql = 'INSERT INTO {p}' . $table . ' (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($data), '?')) . ')';
        self::run($sql, array_values($data));
        return (int)self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $params = []): int
    {
        $sets = [];
        foreach (array_keys($data) as $k) {
            $sets[] = self::ident($k) . ' = ?';
        }
        $sql = 'UPDATE {p}' . $table . ' SET ' . implode(', ', $sets) . ' WHERE ' . $where;
        return self::run($sql, array_merge(array_values($data), $params))->rowCount();
    }

    public static function tx(callable $fn): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $r = $fn();
            $pdo->commit();
            return $r;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Build "IN (?,?,?)" placeholder list. */
    public static function in(array $values): string
    {
        return $values ? implode(',', array_fill(0, count($values), '?')) : 'NULL';
    }
}
