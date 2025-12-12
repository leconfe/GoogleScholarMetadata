<?php

namespace GoogleScholarMetadata;

use App\Classes\Plugin;
use App\Facades\Citation;
use App\Facades\Hook;
use App\Facades\MetaTag;
use App\Frontend\Conference\Pages\Paper;
use App\Models\Submission;
use Filament\Panel;

class GoogleScholarMetadataPlugin extends Plugin
{
	public function boot()
	{
		if (!app()->getCurrentConference()) return false;

		Hook::add('Frontend::Paper::addMetadata', function ($hookName, $livewire, $paper) {
			$this->addMetadata($paper);
		});
	}

	public function addMetadata(Submission $paper)
	{
		$site = app()->getSite();
		$conference = app()->getCurrentConference();

		MetaTag::add('gs_meta_revision', '1.1');
        MetaTag::add('citation_title', e($paper->getMeta('title')));
        MetaTag::add('citation_abstract', strip_tags($paper->getMeta('abstract')));

        $paper->authors->each(function ($author) {
            MetaTag::add('citation_author', $author->fullName);
            if ($author->getMeta('affiliation')) {
                MetaTag::add('citation_author_affiliation', e($author->getMeta('affiliation')));
            }
        });

        if ($paper->isPublished()) {
            MetaTag::add('citation_publication_date', $paper->published_at?->format('Y/m/d'));
            MetaTag::add('citation_date', $paper->published_at?->format('Y/m/d'));
        }

        if ($paper->doi?->doi) {
            MetaTag::add('citation_doi', $paper->doi->doi);
        }

        if ($site->getMeta('publisher_name')) {
            MetaTag::add('citation_publisher', e($site->getMeta('publisher_name')));
        }

        $proceeding = $paper->proceeding;

        MetaTag::add('citation_conference_title', e($conference->name));
        if ($conference->getMeta('issn')) {
            MetaTag::add('citation_issn', e($conference->getMeta('issn')));
        }
        MetaTag::add('citation_volume', e($proceeding->volume));
        MetaTag::add('citation_issue', e($proceeding->number));
        if ($paper) {
            MetaTag::add('citation_section', e($paper->track->title));
        }

        if ($paper->getMeta('article_pages')) {
            $pages = $paper->getMeta('article_pages');
            $parts = explode('-', $pages);

            // Normalize to two elements
            $start = $parts[0] ?? null;
            $end = $parts[1] ?? null;
            if ($start) {
                MetaTag::add('citation_firstpage', $start);
            }

            if ($end) {
                MetaTag::add('citation_lastpage', $end);
            }
        }
        if($paper->getMeta('isbn')){
            MetaTag::add('citation_isbn', $paper->getMeta('isbn'));
        }

        MetaTag::add('citation_abstract_html_url', route(Paper::getRouteName(), ['submission' => $paper->getKey()]));

        $paper->galleys->each(function ($galley) {
            if ($galley->isPdf()) {
                MetaTag::add('citation_pdf_url', $galley->getUrl());
            }
        });

        collect($paper->getMeta('keywords'))
            ->each(fn($keyword) => MetaTag::add('citation_keywords', $keyword));

        collect(explode(PHP_EOL, $paper->getMeta('references')))
            ->filter()
            ->values()
            ->each(fn($reference) => MetaTag::add('citation_reference', $reference));
	}
}
