<?php

namespace Larafolio\Models\ContentTraits;

use Larafolio\Models\Link;

trait HasLinks
{
    /**
     * Get a model from a relationship by model name.
     *
     * @param string $relationship Name of relationship.
     * @param string $name         Name of model to get.
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    abstract protected function getFromRelationshipByName($relationship, $name);

    /**
     * Define a polymorphic one-to-many relationship.
     *
     * @param string $related
     * @param string $name
     * @param string $type
     * @param string $id
     * @param string $localKey
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    abstract public function morphMany($related, $name, $type = null, $id = null, $localKey = null);

    /**
     * A resource has many text blocks.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function links()
    {
        return $this->morphMany(Link::class, 'resource');
    }

    /**
     * Return true if project has links.
     *
     * @return bool
     */
    public function hasLinks()
    {
        return !$this->links->isEmpty();
    }

    /**
     * Get link by name, if exists.
     *
     * @param string $name Name of link to get.
     *
     * @return Larafolio\Models\Link|null
     */
    public function link($name)
    {
        return $this->getFromRelationshipByName('links', $name);
    }

    /**
     * Get link url.
     *
     * @param string $name Name of link.
     *
     * @return string|null
     */
    public function linkUrl($name)
    {
        if (!$link = $this->link($name)) {
            return;
        }

        return $link->url();
    }

    /**
     * Get link text.
     *
     * @param string $name Name of link.
     *
     * @return string|null
     */
    public function linkText($name)
    {
        if (!$link = $this->link($name)) {
            return;
        }

        return $link->text();
    }
}