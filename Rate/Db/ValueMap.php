<?php
namespace Rate\Db;

use Tk\DataMap\Db;
use Tk\DataMap\Form;
use Tk\Db\Map\ArrayObject;
use Tk\Db\Tool;


/**
 * @author Michael Mifsud <info@tropotek.com>
 * @see http://www.tropotek.com/
 * @license Copyright 2015 Michael Mifsud
 */
class ValueMap extends \App\Db\Mapper
{

    /**
     * @param \Tk\Db\Pdo|null $db
     * @throws \Exception
     */
    public function __construct($db = null)
    {
        parent::__construct($db);
        $this->setMarkDeleted('');
    }

    /**
     * @return \Tk\DataMap\DataMap
     */
    public function getDbMap()
    {
        if (!$this->dbMap) {
            $this->setTable('rating_value');
            $this->dbMap = new \Tk\DataMap\DataMap();
            $this->dbMap->addPropertyMap(new Db\Integer('id'), 'key');
            $this->dbMap->addPropertyMap(new Db\Integer('questionId', 'question_id'));
            $this->dbMap->addPropertyMap(new Db\Integer('placementId', 'placement_id'));
            $this->dbMap->addPropertyMap(new Db\Text('value'));
            $this->dbMap->addPropertyMap(new Db\Date('modified'));
            $this->dbMap->addPropertyMap(new Db\Date('created'));
        }
        return $this->dbMap;
    }

    /**
     * @return \Tk\DataMap\DataMap
     */
    public function getFormMap()
    {
        if (!$this->formMap) {
            $this->formMap = new \Tk\DataMap\DataMap();
            $this->formMap->addPropertyMap(new Form\Integer('id'), 'key');
            $this->formMap->addPropertyMap(new Form\Integer('questionId'));
            $this->formMap->addPropertyMap(new Form\Integer('placementId'));
            $this->formMap->addPropertyMap(new Form\Text('value'));
        }
        return $this->formMap;
    }


    /**
     * Remove all values from the DB by placementId
     *
     * @param $placementId
     * @return $this
     * @throws \Exception
     */
    public function removeAllByPlacementId($placementId)
    {
        $list = $this->findFiltered(array('placementId' => $placementId));
        foreach ($list as $v) {
            $v->delete();
        }
        return $this;
    }

    /**
     * @param $questionId
     * @param $placementId
     * @return Value
     * @throws \Exception
     */
    public function findValue($questionId, $placementId)
    {
        return $this->findFiltered(array('questionId' => $questionId, 'placementId' => $placementId))->current();
    }

    /**
     * @param array|\Tk\Db\Filter $filter
     * @param Tool $tool
     * @return ArrayObject|Value[]
     * @throws \Exception
     */
    public function findFiltered($filter, $tool = null)
    {
        return $this->selectFromFilter($this->makeQuery(\Tk\Db\Filter::create($filter)), $tool);
    }

    /**
     * @param \Tk\Db\Filter $filter
     * @return \Tk\Db\Filter
     */
    public function makeQuery(\Tk\Db\Filter $filter)
    {
        $filter->appendFrom('%s a ', $this->quoteParameter($this->getTable()));

        if (!empty($filter['keywords'])) {
            $kw = '%' . $this->getDb()->escapeString($filter['keywords']) . '%';
            $w = '';
            $w .= sprintf('a.value LIKE %s OR ', $this->getDb()->quote($kw));
            if (is_numeric($filter['keywords'])) {
                $id = (int)$filter['keywords'];
                $w .= sprintf('a.id = %d OR ', $id);
            }
            if ($w) $filter->appendWhere('(%s) AND ', substr($w, 0, -3));
        }

        if (!empty($filter['id'])) {
            $w = $this->makeMultiQuery($filter['id'], 'a.id');
            if ($w) $filter->appendWhere('(%s) AND ', $w);
        }

        if (!empty($filter['questionId'])) {
            $filter->appendWhere('a.question_id = %s AND ', (int)$filter['questionId']);
        }

        if (!empty($filter['placementId'])) {
            $filter->appendWhere('a.placement_id = %s AND ', (int)$filter['placementId']);
        }

        if (!empty($filter['courseId'])) {
            $filter->appendFrom(', %s b', $this->quoteTable('rating_question'));
            $filter->appendWhere('a.question_id = b.id AND ');
            $filter->appendWhere('b.course_id = %s AND ', (int)$filter['courseId']);
        }

        if (!empty($filter['subjectId']) || !empty($filter['companyId'])) {
            $filter->appendFrom(', %s c', $this->quoteTable('placement'));
            $filter->appendWhere('a.placement_id = c.id AND ');
            if (!empty($filter['subjectId'])) {
                $filter->appendWhere('c.subject_id = %s AND ', (int)$filter['subjectId']);
            }
            if (!empty($filter['companyId'])) {
                $filter->appendWhere('c.company_id = %s AND ', (int)$filter['companyId']);
            }
        }

        if (!empty($filter['value'])) {
            $filter->appendWhere('a.value = %s AND ', $this->quote($filter['value']));
        }

        if (!empty($filter['dateFrom'])) {
            /** @var \DateTime $dtef */
            $dtef = \Tk\Date::floor($filter['dateFrom']);
            $filter->appendWhere('a.created >= %s AND ', $this->quote($dtef->format(\Tk\Date::FORMAT_ISO_DATETIME)) );
        }
        if (!empty($filter['dateTo'])) {
            /** @var \DateTime $dtet */
            $dtet = \Tk\Date::ceil($filter['dateTo']);
            $filter->appendWhere('a.created <= %s AND ', $this->quote($dtet->format(\Tk\Date::FORMAT_ISO_DATETIME)) );
        }

        if (!empty($filter['exclude'])) {
            $w = $this->makeMultiQuery($filter['exclude'], 'a.id', 'AND', '!=');
            if ($w) $filter->appendWhere('(%s) AND ', $w);
        }

        return $filter;
    }

    /**
     * @param array $filter
     * @param Tool $tool
     * @return float
     */
    public function findAverage($filter, $tool = null)
    {
        $avg = 0;
        try {
            $filter = $this->makeQuery(\Tk\Db\Filter::create($filter));
            $sql = sprintf('SELECT AVG(a.value) as avg
FROM %s
WHERE %s', $filter->getFrom(), $filter->getWhere('1'));
            $stm = $this->getDb()->prepare($sql);
                $stm->execute();
            $r = $stm->fetch();
            $avg = $r->avg;
        } catch (\Exception $e) {}
        return $avg;
    }

}