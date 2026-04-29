<?php

declare(strict_types=1);

namespace Pebblestack\Core;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;
use League\CommonMark\CommonMarkConverter;

/**
 * Twig environment factory. Two namespaces: @admin (admin UI) and @theme
 * (active public theme). Markdown filter for rendering body content.
 */
final class View
{
    private Environment $twig;

    public function __construct(
        string $adminPath,
        string $themePath,
        private readonly Csrf $csrf,
        private readonly Auth $auth,
        private readonly Session $session,
        bool $debug = false,
        ?string $cacheDir = null,
    ) {
        $loader = new FilesystemLoader();
        $loader->addPath($adminPath, 'admin');
        $loader->addPath($themePath, 'theme');
        $cache = false;
        if (!$debug && $cacheDir !== null) {
            // Best-effort: if the cache dir can't be created, fall back to
            // no cache rather than 500. Shared hosts sometimes have unusual
            // permissions on data/.
            if (is_dir($cacheDir) || @mkdir($cacheDir, 0775, true)) {
                if (is_writable($cacheDir)) {
                    $cache = $cacheDir;
                }
            }
        }
        $this->twig = new Environment($loader, [
            'autoescape' => 'html',
            'strict_variables' => false,
            'cache' => $cache,
            'auto_reload' => true,
            'debug' => $debug,
        ]);

        $markdown = new CommonMarkConverter([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);
        $this->twig->addFilter(new TwigFilter('markdown', function (?string $text) use ($markdown) {
            if ($text === null || $text === '') {
                return '';
            }
            return $markdown->convert($text)->getContent();
        }, ['is_safe' => ['html']]));

        $this->twig->addFunction(new TwigFunction('csrf_token', fn () => $this->csrf->token()));
        $this->twig->addFunction(new TwigFunction('csrf_field', fn () =>
            '<input type="hidden" name="_csrf" value="' . htmlspecialchars($this->csrf->token(), ENT_QUOTES) . '">',
            ['is_safe' => ['html']]
        ));
        $this->twig->addFunction(new TwigFunction('current_user', fn () => $this->auth->user()));
        $this->twig->addFunction(new TwigFunction('flash', fn (string $key) => $this->session->flash($key)));
    }

    /** @param array<string,mixed> $context */
    public function render(string $name, array $context = []): string
    {
        return $this->twig->render($name, $context);
    }

    public function exists(string $name): bool
    {
        return $this->twig->getLoader()->exists($name);
    }

    public function env(): Environment
    {
        return $this->twig;
    }
}
