<?php
/**
 * @file classes/institution/Collector.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class institution
 *
 * @brief A helper class to configure a Query Builder to get a collection of institutions
 */

namespace PKP\institution;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use PKP\core\interfaces\CollectorInterface;
use PKP\plugins\HookRegistry;

class Collector implements CollectorInterface
{
    /** @var DAO */
    public $dao;

    /** @var array|null */
    public $contextIds = null;

    /** @var string */
    public $searchPhrase = '';

    /** @var int */
    public $count;

    /** @var int */
    public $offset;

    /** @var bool */
    public $considerSoftDeletes = false;

    /** @var bool */
    public $onlySoftDeletes = false;


    public function __construct(DAO $dao)
    {
        $this->dao = $dao;
    }

    /**
     * Filter institutions by one or more contexts
     */
    public function filterByContextIds(array $contextIds): self
    {
        $this->contextIds = $contextIds;
        return $this;
    }

    /**
     * Filter soft deleted institutions
     */
    public function filterSoftDeletes(): self
    {
        $this->onlySoftDeletes = true;
        return $this;
    }

    /**
     * Filter institutions by those matching a search query
     */
    public function searchPhrase(string $phrase): self
    {
        $this->searchPhrase = $phrase;
        return $this;
    }

    /**
     * Limit the number of objects retrieved
     */
    public function limit(int $count): self
    {
        $this->count = $count;
        return $this;
    }

    /**
     * Offset the number of objects retrieved, for example to
     * retrieve the second page of contents
     */
    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * Consider soft deleted institutions
     */
    public function considerSoftDeletes(): self
    {
        $this->considerSoftDeletes = true;
        return $this;
    }

    /**
     * @copydoc CollectorInterface::getQueryBuilder()
     */
    public function getQueryBuilder(): Builder
    {
        $qb = DB::table($this->dao->table . ' as i');

        if (is_array($this->contextIds)) {
            $qb->whereIn('i.context_id', $this->contextIds);
        }

        if ($this->onlySoftDeletes) {
            $qb->whereNotNull('deleted_at');
        } elseif (!$this->considerSoftDeletes) {
            $qb->whereNull('deleted_at');
        }

        if (!empty($this->searchPhrase)) {
            $words = explode(' ', $this->searchPhrase);
            if (count($words)) {
                foreach ($words as $word) {
                    $word = addcslashes($word, '%_');
                    $qb->where(function ($qb) use ($word) {
                        $qb->whereIn('i.institution_id', function ($qb) use ($word) {
                            $qb->select('iss.institution_id')
                                ->from($this->dao->settingsTable . ' as iss')
                                ->where('iss.setting_name', '=', 'name')
                                ->where('iss.setting_value', 'LIKE', "%{$word}%");
                        })
                            ->orWhereIn('i.institution_id', function ($qb) use ($word) {
                                $qb->select('ip.institution_id')
                                    ->from('institution_ip as ip')
                                    ->where('ip.ip_string', 'LIKE', "%{$word}%");
                            });
                    });
                }
            }
        }

        $qb->select('i.*')->get();

        if (!empty($this->count)) {
            $qb->limit($this->count);
        }

        if (!empty($this->offset)) {
            $qb->offset($this->count);
        }

        HookRegistry::call('Institution::Collector', [&$qb, $this]);

        return $qb;
    }
}
