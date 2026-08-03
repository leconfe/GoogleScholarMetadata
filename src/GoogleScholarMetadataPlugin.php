<?php

namespace GoogleScholarMetadata;

use App\Classes\Plugin;
use App\Facades\Hook;
use App\Facades\MetaTag;
use App\Models\Submission;
use Illuminate\Support\Str;

class GoogleScholarMetadataPlugin extends Plugin
{
    public function boot()
    {
        Hook::add('Frontend::Paper::addMetadata', function ($hookName, $livewire, $paper) {
            if ($paper instanceof Submission) {
                $this->addMetadata($paper);
            }
        });
    }

    public function addMetadata(Submission $paper)
    {
        $site = app()->getSite();
        $conference = app()->getCurrentConference() ?? app()->getCurrentScheduledConference();

        MetaTag::add('gs_meta_revision', '1.1');
        MetaTag::add('citation_title', e($paper->getMeta('title')));

        if ($paper->getMeta('abstract')) {
            MetaTag::add('citation_abstract', strip_tags($paper->getMeta('abstract')));
        }

        $paper->authors->each(function ($author) {
            $authorName = $author->fullName ?? Str::squish(($author->given_name ?? '').' '.($author->family_name ?? ''));
            if (! empty($authorName)) {
                MetaTag::add('citation_author', e($authorName));
            }
            if ($author->getMeta('affiliation')) {
                MetaTag::add('citation_author_affiliation', e($author->getMeta('affiliation')));
            }
        });

        if ($paper->isPublished() && $paper->published_at) {
            $dateStr = $paper->published_at->format('Y/m/d');

            MetaTag::add('citation_publication_date', $dateStr);
            MetaTag::add('citation_date', $dateStr);
        }

        if ($paper->doi?->doi) {
            MetaTag::add('citation_doi', $paper->doi->doi);
        }

        if ($site && $site->getMeta('publisher_name')) {
            MetaTag::add('citation_publisher', e($site->getMeta('publisher_name')));
        }

        $proceeding = $paper->proceeding;

        if ($conference) {
            MetaTag::add('citation_conference_title', e($conference->name ?? $conference->getMeta('name') ?? ''));
            if ($conference->getMeta('issn')) {
                MetaTag::add('citation_issn', e($conference->getMeta('issn')));
            }
        }

        if ($proceeding) {
            if ($proceeding->volume) {
                MetaTag::add('citation_volume', e($proceeding->volume));
            }
            if ($proceeding->number) {
                MetaTag::add('citation_issue', e($proceeding->number));
            }
            if ($proceeding->getMeta('isbn')) {
                MetaTag::add('citation_isbn', e($proceeding->getMeta('isbn')));
            }
        }

        if ($paper->track) {
            MetaTag::add('citation_section', e($paper->track->title ?? ''));
        }

        if ($paper->getMeta('article_pages')) {
            $pages = $paper->getMeta('article_pages');
            $parts = explode('-', $pages);

            $start = $parts[0] ?? null;
            $end = $parts[1] ?? null;
            if ($start) {
                MetaTag::add('citation_firstpage', trim($start));
            }

            if ($end) {
                MetaTag::add('citation_lastpage', trim($end));
            }
        }

        if ($paper->getMeta('isbn')) {
            MetaTag::add('citation_isbn', e($paper->getMeta('isbn')));
        }

        try {
            MetaTag::add('citation_abstract_html_url', url()->current());
        } catch (\Throwable $th) {
            // Safe fallback
        }

        $paper->galleys->each(function ($galley) {
            if ($galley->isPdf()) {
                try {
                    MetaTag::add('citation_pdf_url', $galley->getUrl());
                } catch (\Throwable $th) {
                    // Safe fallback
                }
            }
        });

        if (is_array($paper->getMeta('keywords'))) {
            collect($paper->getMeta('keywords'))
                ->each(fn ($keyword) => MetaTag::add('citation_keywords', e($keyword)));
        }

        if ($paper->getMeta('references')) {
            collect(explode(PHP_EOL, (string) $paper->getMeta('references')))
                ->map(fn ($ref) => trim($ref))
                ->filter()
                ->values()
                ->each(fn ($reference) => MetaTag::add('citation_reference', e($reference)));
        }
    }
}
