<?php

namespace x\markdown\page {
    function content($content) {
        $type = $this->type;
        if ('Markdown' !== $type && 'text/markdown' !== $type) {
            return $content;
        }
        $out = new \ParsedownExtraPlugin;
        foreach (\State::get('x.markdown', true) ?? [] as $k => $v) {
            $out->{$k} = $v;
        }
        return "" !== ($out = $out->text($content ?? "")) ? $out : null;
    }
    function description($description) {
        return \fire(__NAMESPACE__ . "\\title", [$description], $this);
    }
    function title($title) {
        $type = $this->type;
        if ('Markdown' !== $type && 'text/markdown' !== $type) {
            return $title;
        }
        $out = new \ParsedownExtraPlugin;
        foreach (\State::get('x.markdown', true) ?? [] as $k => $v) {
            if (0 === \strpos($k, 'block')) {
                continue;
            }
            $out->{$k} = $v;
        }
        return "" !== ($out = $out->line($title ?? "")) ? $out : null;
    }
    \Hook::set('page.content', __NAMESPACE__ . "\\content", 2);
    // Inline tag(s) only
    \Hook::set('page.description', __NAMESPACE__ . "\\description", 2);
    \Hook::set('page.title', __NAMESPACE__ . "\\title", 2);
}