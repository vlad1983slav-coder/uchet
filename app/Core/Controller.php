<?php

declare(strict_types=1);

namespace Uchet\Core;

/**
 * Базовый контроллер: рендер, JSON, редиректы, проверка прав, CSRF, валидация.
 * Наследники — тонкие: получают данные из Request, вызывают сервисы, отдают View/JSON.
 */
abstract class Controller
{
    /**
     * Рендер view (по умолчанию внутри layouts.main; если layout не создан — без него).
     *
     * @param array<string, mixed> $data
     */
    protected function view(string $template, array $data = [], ?string $layout = 'layouts.main'): string
    {
        if ($layout !== null && View::exists($layout)) {
            return View::make($template, $data, $layout);
        }

        return View::render($template, $data);
    }

    /** @param array<string, mixed> $data */
    protected function render(string $template, array $data = [], ?string $layout = 'layouts.main'): never
    {
        Response::html($this->view($template, $data, $layout));
    }

    protected function json(mixed $data, int $status = 200): never
    {
        Response::json($data, $status);
    }

    protected function redirect(string $url, int $code = 302): never
    {
        Response::redirect($url, $code);
    }

    /** Редирект на Referer (или на '/'). */
    protected function back(): never
    {
        $referer = Request::referer();
        Response::redirect($referer !== '' ? $referer : '/');
    }

    /** @throws ForbiddenException */
    protected function authorize(string $permission): void
    {
        Auth::requirePermission($permission);
    }

    /** @throws HttpException(405) */
    protected function requirePost(): void
    {
        if (!Request::isPost()) {
            throw new HttpException(405);
        }
    }

    /** @throws CsrfException */
    protected function csrf(): void
    {
        Csrf::check();
    }

    /**
     * Валидация входных данных.
     * При успехе возвращает только проверенные поля.
     * При ошибке: AJAX → JSON 422; обычная форма → flash errors+old input и редирект назад.
     *
     * @param array<string, string|string[]> $rules
     * @return array<string, mixed>
     */
    protected function validate(array $rules, ?array $data = null): array
    {
        $data ??= str_contains((string) Request::header('Content-Type'), 'application/json')
            ? Request::json()
            : Request::all();

        $validator = Validator::make($data, $rules);
        if ($validator->validate()) {
            return $validator->validated();
        }

        if (Request::wantsJson()) {
            Response::json(['ok' => false, 'errors' => $validator->errors()], 422);
        }

        Session::flash('errors', $validator->errors());
        Session::flashInput($data);
        $referer = Request::referer();
        Response::redirect($referer !== '' ? $referer : '/');
    }

    protected function flash(string $type, string $message): void
    {
        Session::flash($type, $message);
    }

    protected function user(): ?array
    {
        return Auth::user();
    }
}
