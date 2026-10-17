<?php

namespace Lambda\H5P\Repositories;

use Brnysn\LaravelH5P\Repositories\H5PRepository;

class H5PFixedRepository extends H5PRepository
{
    /**
     * The vendor H5PContentRepository::edit() passes 'filtered' as a decoded
     * array; the H5PContent model has no cast for it, so every content UPDATE
     * died with "Array to string conversion" (content create never sets the
     * key, which is why only re-saving failed). Stringify before persisting.
     */
    public function updateContent($content, $contentMainId = null)
    {
        if (isset($content['filtered']) && !is_string($content['filtered'])) {
            $content['filtered'] = json_encode($content['filtered']);
        }

        return parent::updateContent($content, $contentMainId);
    }

    /**
     * Vendor version returns config('app.url').'/storage/h5p/...' — wrong
     * host when APP_URL is stale and a path nothing serves, so installed
     * content types lost their icons in the hub picker. Files actually live
     * under public/h5p → storage/app/h5p.
     */
    public function getLibraryFileUrl($libraryFolderName, $fileName)
    {
        return url(config('hh5p.url', 'h5p') . '/libraries/' . $libraryFolderName . '/' . $fileName);
    }
}
