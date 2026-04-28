<?php

/**
 * Collections define your content shape. Add, remove, or rename them — the
 * admin UI updates automatically. Field types: text, textarea, markdown,
 * slug, boolean, number, select, datetime, url.
 */

return [

    'pages' => [
        'label'          => 'Pages',
        'label_singular' => 'Page',
        'icon'           => 'file',
        'route'          => '/{slug}',
        'template'       => 'page.twig',
        'order_by'       => 'updated_at DESC',
        'fields' => [
            'title'            => ['type' => 'text', 'required' => true, 'label' => 'Title'],
            'slug'             => ['type' => 'slug', 'required' => true, 'label' => 'Slug', 'help' => 'URL path, lowercase letters, numbers, dashes.'],
            'body'             => ['type' => 'markdown', 'label' => 'Body', 'help' => 'Markdown supported.'],
            'meta_description' => ['type' => 'textarea', 'label' => 'Meta description', 'help' => 'Used in <meta name="description">. ~160 chars.'],
        ],
    ],

    'posts' => [
        'label'          => 'Posts',
        'label_singular' => 'Post',
        'icon'           => 'edit',
        'route'          => '/blog/{slug}',
        'template'       => 'post.twig',
        'list_template'  => 'post-list.twig',
        'order_by'       => 'publish_at DESC',
        'fields' => [
            'title'   => ['type' => 'text', 'required' => true, 'label' => 'Title'],
            'slug'    => ['type' => 'slug', 'required' => true, 'label' => 'Slug'],
            'excerpt' => ['type' => 'textarea', 'label' => 'Excerpt', 'help' => 'Short summary for list pages and meta description.'],
            'body'    => ['type' => 'markdown', 'required' => true, 'label' => 'Body'],
            'author'  => ['type' => 'text', 'label' => 'Author'],
        ],
    ],

    // Example contact form. Mark a collection as 'is_form' => true to turn
    // it into a public submission endpoint at POST /forms/{name}. The admin
    // shows received submissions instead of an editor.
    'contact' => [
        'label'          => 'Contact',
        'label_singular' => 'Submission',
        'is_form'        => true,
        'fields' => [
            'name'    => ['type' => 'text', 'required' => true, 'label' => 'Name'],
            'email'   => ['type' => 'text', 'required' => true, 'label' => 'Email'],
            'message' => ['type' => 'textarea', 'required' => true, 'label' => 'Message'],
        ],
    ],

];
