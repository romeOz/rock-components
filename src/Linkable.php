<?php

namespace rock\components;


/**
 * Linkable is the interface that should be implemented by classes that typically represent locatable resources.
 */
interface Linkable
{
    /**
     * Returns a list of links.
     *
     * Each link is either a URI or a {@see \rock\helpers\Link} object. The return value of this method should
     * be an array whose keys are the relation names and values the corresponding links.
     *
     * If a relation name corresponds to multiple links, use an array to represent them.
     *
     * For example,
     *
     * ```php
     * [
     *     'self' => 'http://example.com/users/1',
     *     'friends' => [
     *         'http://example.com/users/2',
     *         'http://example.com/users/3',
     *     ],
     * ]
     * ```
     *
     * @return array the links
     */
    public function getLinks();
}
