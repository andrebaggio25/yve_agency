<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected function view(string $template, array $data = [], int $status = 200): Response
    {
        return Response::view($template, $data, $status);
    }

    protected function json(mixed $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    protected function redirect(string $url, int $status = 302): Response
    {
        return Response::redirect($url, $status);
    }

    protected function back(): Response
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        return Response::redirect($referer);
    }

    protected function withSuccess(string $message): static
    {
        flash('success', $message);
        return $this;
    }

    protected function withError(string $message): static
    {
        flash('error', $message);
        return $this;
    }

    protected function withErrors(array $errors): static
    {
        flash('errors', $errors);
        return $this;
    }

    protected function withInput(array $input): static
    {
        flash('old', $input);
        return $this;
    }
}
