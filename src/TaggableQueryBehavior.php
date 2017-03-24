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
use yii\db\Expression;

/**
 * Class TaggableQueryBehavior
 *
 * @package yongtiger\taggable
 */
class TaggableQueryBehavior extends Behavior
{
    /**
     * Gets entities by any tags.
     * @param string|string[] $values
     * @param string|null $attribute
     * @return \yii\db\ActiveQuery the owner
     */
    public function anyTagValues($values, $attribute = null)
    {
        $model = new $this->owner->modelClass();
        $tagClass = $model->getRelation($model->tagRelation)->modelClass;

        $this->owner
            ->innerJoinWith($model->tagRelation, false)
            ->andWhere([$tagClass::tableName() . '.' . ($attribute ?: $model->tagValueAttribute) => $model->filterTagValues($values)])
            ->addGroupBy(array_map(function ($pk) use ($model) { return $model->tableName() . '.' . $pk; }, $model->primaryKey()));

        return $this->owner;
    }

    /**
     * Gets entities by all tags.
     * @param string|string[] $values
     * @param string|null $attribute
     * @return \yii\db\ActiveQuery the owner
     */
    public function allTagValues($values, $attribute = null)
    {
        $model = new $this->owner->modelClass();

        return $this->anyTagValues($values, $attribute)->andHaving(new Expression('COUNT(*) = ' . count($model->filterTagValues($values))));
    }

    /**
     * Gets entities related by tags.
     * @param string|string[] $values
     * @param string|null $attribute
     * @return \yii\db\ActiveQuery the owner
     */
    public function relatedByTagValues($values, $attribute = null)
    {
        return $this->anyTagValues($values, $attribute)->addOrderBy(new Expression('COUNT(*) DESC'));
    }
}
