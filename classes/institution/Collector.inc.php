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

        if (!$this->considerSoftDeletes) {
            $qb->whereNull('deleted_at');
        }

        if (!empty($this->searchPhrase)) {
            $words = explode(' ', $this->searchPhrase);
            if (count($words)) {
                $qb->leftJoin($this->dao->settingsTable . ' as isrch', 'i.institution_id', '=', 'isrch.institution_id')
                    ->leftJoin('institution_ip as ip', 'ip.institution_id', '=', 'i.institution_id');
                foreach ($words as $word) {
                    $word = strtolower(addcslashes($word, '%_'));
                    $qb->where(function ($qb) use ($word) {
                        $qb->where(function ($qb) use ($word) {
                            $qb->where('isrch.setting_name', 'name');
                            $qb->where(DB::raw('lower(isrch.setting_value)'), 'LIKE', "%{$word}%");
                        })
                            ->orWhere(function ($qb) use ($word) {
                                $qb->where(DB::raw('lower(ip.ip_string)'), 'LIKE', "%{$word}%");
                            });
                    });
                }
            }
        }

        $qb->groupBy('i.institution_id');

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
