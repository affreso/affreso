<?php
declare(strict_types=1);

final class Router
{
    private array $routes = [];

    public function get(string $pattern, array $handler, array $opts = []): void
    {
        $this->add('GET', $pattern, $handler, $opts);
    }

    public function post(string $pattern, array $handler, array $opts = []): void
    {
        $this->add('POST', $pattern, $handler, $opts);
    }

    private function add(string $method, string $pattern, array $handler, array $opts): void
    {
        $regex = '#^' . preg_replace('#\{([a-z_]+)\}#', '(?P<$1>[A-Za-z0-9_\-]+)', $pattern) . '$#';
        $this->routes[] = compact('method', 'pattern', 'regex', 'handler', 'opts');
    }

    public function dispatch(string $method, string $path): void
    {
        $path = '/' . trim($path, '/');
        $allowed = false;
        foreach ($this->routes as $r) {
            if (!preg_match($r['regex'], $path, $m)) {
                continue;
            }
            if ($r['method'] !== $method) {
                $allowed = true;
                continue;
            }
            $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            $o = $r['opts'];
            if ($method === 'POST' && empty($o['no_csrf'])) {
                verify_csrf();
            }
            if (empty($o['public'])) {
                require_login();
            }
            if (!empty($o['perm'])) {
                require_perm($o['perm']);
            }
            [$class, $fn] = $r['handler'];
            $ctrl = new $class();
            $ctrl->$fn(...array_values($params));
            return;
        }
        abort($allowed ? 405 : 404);
    }
}
