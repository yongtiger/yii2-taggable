<?php ///[yii2-taggable]

/**
 * Yii2 taggable
 *
 * @link        http://www.brainbook.cc
 * @see         https://github.com/yongtiger/yii2-taggable
 * @author      Tiger Yong <tigeryang.brainbook@outlook.com>
 * @copyright   Copyright (c) 2017 BrainBook.CC
 * @license     http://opensource.org/licenses/MIT
 */

namespace yongtiger\taggable;

use yii\base\Behavior;
use yii\db\ActiveRecord;
use yii\db\Query;

/**
 * Class TaggableBehavior
 *
 * @package yongtiger\taggable
 */
class TaggableBehavior extends Behavior
{
    /**
     * @var boolean whether to return tags as array instead of string
     */
    public $tagValuesAsArray = false;
    /**
     * @var string the tags relation name
     */
    public $tagRelation = 'tags';
    /**
     * @var string the tags model value attribute name
     */
    public $tagValueAttribute = 'name';
    /**
     * @var string|false the tags model frequency attribute name
     */
    public $tagFrequencyAttribute = 'frequency';
    /**
     * @var string[]
     */
    private $_tagValues;

    /**
     * @inheritdoc
     */
    public function events()
    {
        return [
            ActiveRecord::EVENT_AFTER_INSERT => 'afterSave',
            ActiveRecord::EVENT_AFTER_UPDATE => 'afterSave',
            ActiveRecord::EVENT_BEFORE_DELETE => 'beforeDelete',
        ];
    }

    /**
     * Returns tags.
     * @param boolean|null $asArray
     * @return string|string[]
     */
    public function getTagValues($asArray = null)
    {
        if (!$this->owner->getIsNewRecord() && $this->_tagValues === null) {
            $this->_tagValues = [];

            /* @var ActiveRecord $tag */
            foreach ($this->owner->{$this->tagRelation} as $tag) {
                $this->_tagValues[] = $tag->getAttribute($this->tagValueAttribute);
            }
        }

        if ($asArray === null) {
            $asArray = $this->tagValuesAsArray;
        }

        if ($asArray) {
            return $this->_tagValues === null ? [] : $this->_tagValues;
        } else {
            return $this->_tagValues === null ? '' : implode(', ', $this->_tagValues);
        }
    }

    /**
     * Sets tags.
     * @param string|string[] $values
     */
    public function setTagValues($values)
    {
        $this->_tagValues = $this->filterTagValues($values);
    }

    /**
     * Adds tags.
     * @param string|string[] $values
     */
    public function addTagValues($values)
    {
        $this->_tagValues = array_unique(array_merge($this->getTagValues(true), $this->filterTagValues($values)));
    }

    /**
     * Removes tags.
     * @param string|string[] $values
     */
    public function removeTagValues($values)
    {
        $this->_tagValues = array_diff($this->getTagValues(true), $this->filterTagValues($values));
    }

    /**
     * Removes all tags.
     */
    public function removeAllTagValues()
    {
        $this->_tagValues = [];
    }

    /**
     * Returns a value indicating whether tags exists.
     * @param string|string[] $values
     * @return boolean
     */
    public function hasTagValues($values)
    {
        $tagValues = $this->getTagValues(true);

        foreach ($this->filterTagValues($values) as $value) {
            if (!in_array($value, $tagValues)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return void
     */
    ///[yii2-brainblog_v0.4.1_f0.3.3_tag][BUG:creocoder\yii2-taggable]新建记录的isNewRecord为真，但save后就变为false了！
    ///public function afterSave()
    public function afterSave($event)

    {
        if ($this->_tagValues === null) {
            return;
        }

        ///[yii2-brainblog_v0.4.1_f0.3.3_tag][BUG:creocoder\yii2-taggable]新建记录的isNewRecord为真，但save后就变为false了！
        ///if (!$this->owner->isNewRecord) {
        if ($event->name != ActiveRecord::EVENT_AFTER_INSERT) {

            $this->beforeDelete();
        }

        $tagRelation = $this->owner->getRelation($this->tagRelation);
        $pivot = $tagRelation->via->from[0];
        /* @var ActiveRecord $class */
        $class = $tagRelation->modelClass;
        $rows = [];

        foreach ($this->_tagValues as $value) {
            /* @var ActiveRecord $tag */
            $tag = $class::findOne([$this->tagValueAttribute => $value]);

            if ($tag === null) {
                $tag = new $class();
                $tag->setAttribute($this->tagValueAttribute, $value);
            }

            if ($this->tagFrequencyAttribute !== false) {
                $frequency = $tag->getAttribute($this->tagFrequencyAttribute);
                $tag->setAttribute($this->tagFrequencyAttribute, ++$frequency);
            }

            if ($tag->save()) {
                $rows[] = [$this->owner->getPrimaryKey(), $tag->getPrimaryKey()];
            }
        }

        if (!empty($rows)) {
            $this->owner->getDb()
                ->createCommand()
                ->batchInsert($pivot, [key($tagRelation->via->link), current($tagRelation->link)], $rows)
                ->execute();
        }
    }

    /**
     * @return void
     */
    public function beforeDelete()
    {
        $tagRelation = $this->owner->getRelation($this->tagRelation);
        $pivot = $tagRelation->via->from[0];

        if ($this->tagFrequencyAttribute !== false) {
            /* @var ActiveRecord $class */
            $class = $tagRelation->modelClass;

            $pks = (new Query())
                ->select(current($tagRelation->link))
                ->from($pivot)
                ->where([key($tagRelation->via->link) => $this->owner->getPrimaryKey()])
                ->column($this->owner->getDb());

            if (!empty($pks)) {
                $class::updateAllCounters([$this->tagFrequencyAttribute => -1], ['in', $class::primaryKey(), $pks]);
            }
        }

        $this->owner->getDb()
            ->createCommand()
            ->delete($pivot, [key($tagRelation->via->link) => $this->owner->getPrimaryKey()])
            ->execute();

        ///[yii2-brainblog_v0.4.1_f0.3.3_tag][BUG:creocoder\yii2-taggable]删除文章时， frequency变为0，而不是删除tag记录！
        $class::deleteAll('frequency = 0');

    }

    /**
     * Filters tags.
     * @param string|string[] $values
     * @return string[]
     */
    public function filterTagValues($values)
    {
        return array_unique(preg_split(
            '/\s*,\s*/u',

            ///[yii2-brainblog_v0.4.2_f0.4.1_tag_input][BUG]输入带空格的标签（如“   gg  gg  ”）产生首尾带空格的标签！
            ///preg_replace('/\s+/u', ' ', is_array($values) ? implode(',', $values) : $values),
            preg_replace('/\s+/u', '', is_array($values) ? implode(',', $values) : $values),

            -1,
            PREG_SPLIT_NO_EMPTY
        ));
    }
}
