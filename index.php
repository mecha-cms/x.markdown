<?php namespace x\markdown;

function page__content($content) {
    $type = $this->type;
    if ('Markdown' !== $type && 'text/markdown' !== $type) {
        return $content;
    }
    return from($content);
}

function page__description($description) {
    return \fire(__NAMESPACE__ . "\\page__title", [$description], $this);
}

function page__title($title) {
    $type = $this->type;
    if ('Markdown' !== $type && 'text/markdown' !== $type) {
        return $title;
    }
    return from($title, false);
}

\Hook::set('page.content', __NAMESPACE__ . "\\page__content", 2);
\Hook::set('page.description', __NAMESPACE__ . "\\page__description", 2);
\Hook::set('page.title', __NAMESPACE__ . "\\page__title", 2);