<?php namespace x\markdown;

function page__content($content) {
    $type = $this->type();
    if ('Markdown' !== $type && 'text/markdown' !== $type) {
        return $content;
    }
    $content = \strtr(\From::markdown($content) ?? "", [' />' => '>']);
    return "" !== $content ? $content : null;
}

function page__description($description) {
    return \fire(__NAMESPACE__ . "\\page__title", [$description], $this);
}

function page__title($title) {
    $type = $this->type();
    if ('Markdown' !== $type && 'text/markdown' !== $type) {
        return $title;
    }
    $title = \strtr(\From::markdown($title, false) ?? "", [' />' => '>']);
    return "" !== $title ? $title : null;
}

function page__type($type) {
    if (false !== \strpos(',markdown,md,mkd,', ',' . ($this->_x() ?? P) . ',')) {
        return 'Markdown';
    }
    return $type;
}

\Hook::set('page.content', __NAMESPACE__ . "\\page__content", 2);
\Hook::set('page.description', __NAMESPACE__ . "\\page__description", 2);
\Hook::set('page.title', __NAMESPACE__ . "\\page__title", 2);
\Hook::set('page.type', __NAMESPACE__ . "\\page__type", 0);