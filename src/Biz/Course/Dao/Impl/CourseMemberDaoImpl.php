<?php

namespace Biz\Course\Dao\Impl;

use Biz\Course\Dao\CourseMemberDao;
use Codeages\Biz\Framework\Dao\GeneralDaoImpl;
use Codeages\Biz\Framework\Dao\DynamicQueryBuilder;

class CourseMemberDaoImpl extends GeneralDaoImpl implements CourseMemberDao
{
    protected $table = 'course_member';
    protected $alias = 'm';

    public function findByCourseId($courseId)
    {
        $sql = "SELECT * FROM {$this->table()} WHERE courseId = ?";
        return $this->db()->executeQuery($sql, array($courseId))->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function findByUserId($userId)
    {
        $sql = "SELECT * FROM {$this->table()} WHERE userId = ?";
        return $this->db()->executeQuery($sql, array($userId))->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function findByCourseIds($courseIds)
    {
        $marks = str_repeat('?,', count($courseIds) - 1).'?';
        $sql   = "SELECT * FROM {$this->table()} WHERE courseId IN ({$marks})";
        return $this->db()->fetchAll($sql, $courseIds);
    }

    public function getByCourseIdAndUserId($courseId, $userId)
    {
        $sql = "SELECT * FROM {$this->table()} WHERE courseId = ? and userId = ? LIMIT 1";
        return $this->db()->fetchAssoc($sql, array($courseId, $userId)) ?: null;
    }

    public function findLearnedByCourseIdAndUserId($courseId, $userId)
    {
        $sql = "SELECT * FROM {$this->table()} WHERE courseId = ? AND userId = ? AND isLearned = 1";
        return $this->db()->fetchAll($sql, array($courseId, $userId));
    }

    public function findByCourseIdAndRole($courseId, $role)
    {
        $sql = "SELECT * FROM {$this->table()} WHERE courseId = ? AND role = ? ORDER BY seq, createdTime DESC";

        return $this->db()->fetchAll($sql, array($courseId, $role));
    }

    public function findByUserIdAndJoinType($userId, $joinedType)
    {
        $sql = "SELECT * FROM {$this->table()} WHERE  userId = ? AND joinedType = ?";
        return $this->db()->fetchAll($sql, array($userId, $joinedType));
    }

    public function deleteByCourseIdAndRole($courseId, $role)
    {
        return $this->db()->delete($this->table(), array('courseId' => $courseId, 'role' => $role));
    }

    public function deleteByCourseId($courseId)
    {
        return $this->db()->delete($this->table(), array('courseId' => $courseId));
    }

    public function findByUserIdAndCourseIds($studentId, $courseIds)
    {
        $marks = str_repeat('?,', count($courseIds) - 1).'?';
        $sql   = "SELECT * FROM {$this->table()} WHERE userId = ? AND role = 'student' AND courseId in ($marks)";
        return $this->db()->fetchAll($sql, array_merge(array($studentId), $courseIds));
    }

    public function countLearningMembersByUserId($userId)
    {
        $sql = "SELECT COUNT(m.id) FROM {$this->table()} m ";
        $sql .= "INNER JOIN c2_course c ON m.courseId = c.id ";
        $sql .= "WHERE m.userId = ? AND  m.role = 'student' AND (m.learnedNum < c.publishedTaskNum OR c.serializeMode = 'serialized')";
        return $this->db()->fetchColumn($sql, array($userId));
    }

    public function findLearningMembers($userId, $start, $limit)
    {
        $sql = "SELECT m.* FROM {$this->table()} m ";
        $sql .= "INNER JOIN c2_course c ON m.courseId = c.id ";
        $sql .= "WHERE m.userId = ? AND  m.role = 'student' AND (m.learnedNum < c.publishedTaskNum OR c.serializeMode = 'serialized') ";
        $sql .= "ORDER BY createdTime DESC LIMIT {$start}, {$limit}";
        return $this->db()->fetchAll($sql, array($userId)) ?: array();
    }

    public function countLearnedMembersByUserId($userId)
    {
        $sql = "SELECT COUNT(m.id) FROM {$this->table()} m ";
        $sql .= "INNER JOIN c2_course c ON m.courseId = c.id ";
        $sql .= "WHERE m.userId = ? AND  m.role = 'student' AND m.learnedNum >= c.publishedTaskNum  AND c.serializeMode IN ( 'none','finished') ";

        return $this->db()->fetchColumn($sql, array($userId));
    }

    public function findLearnedMembers($userId, $start, $limit)
    {
        $sql = "SELECT m.* FROM {$this->table()} m ";
        $sql .= "INNER JOIN c2_course c ON m.courseId = c.id ";
        $sql .= "WHERE m.userId = ? AND  m.role = 'student' AND m.learnedNum >= c.publishedTaskNum  AND c.serializeMode IN ( 'none','finished') ";
        $sql .= "ORDER BY createdTime DESC LIMIT {$start}, {$limit}";

        return $this->db()->fetchAll($sql, array($userId)) ?: array();
    }

    public function searchMemberCountGroupByFields($conditions, $groupBy, $start, $limit)
    {
        $builder = $this->_createQueryBuilder($conditions)
            ->select("{$groupBy}, COUNT(id) AS count")
            ->groupBy($groupBy)
            ->orderBy('count', 'DESC')
            ->setFirstResult($start)
            ->setMaxResults($limit);

        return $builder->execute()->fetchAll() ?: array();
    }

    public function findByUserIdAndRole($userId, $role)
    {
        $sql = "SELECT * FROM {$this->table()} WHERE userId = ? AND role =  ?";
        return $this->db()->fetchAll($sql, array($userId, $role));
    }

    /**
     * @param  $userId
     * @param  $courseSetId
     * @param  $role
     * @return array
     */
    public function findByUserIdAndCourseSetIdAndRole($userId, $courseSetId, $role)
    {
        return $this->findByFields(array(
            'userId'      => $userId,
            'courseSetId' => $courseSetId,
            'role'        => $role
        ));
    }

    public function findMembersNotInClassroomByUserIdAndRole($userId, $role, $start, $limit, $onlyPublished = true)
    {
        $sql = "SELECT m.* FROM {$this->table} m ";
        $sql .= ' JOIN  c2_course AS c ON m.userId = ? ';
        $sql .= " AND m.role =  ? AND m.courseId = c.id AND c.parentId = 0";

        if ($onlyPublished) {
            $sql .= " AND c.status = 'published' ";
        }

        $sql .= " ORDER BY createdTime DESC LIMIT {$start}, {$limit}";

        return $this->db()->fetchAll($sql, array($userId, $role));
    }

    public function searchMemberIds($conditions, $orderBy, $start, $limit)
    {
        $builder = $this->_createQueryBuilder($conditions);

        if (isset($conditions['unique'])) {
            $builder->select('userId');
            $builder->orderBy($orderBy[0], $orderBy[1]);
            $builder->from('('.$builder->getSQL().')', $this->table());
            //when we use distinct in strict mode, it's not allowed to order by field that is not in select part,
            //so we use a sub query, and reset result field here.
            $builder->select('distinct userId');
            $builder->resetQueryPart('where');
            $builder->resetQueryPart('orderBy');
        } else {
            $builder->select('userId');
            $builder->orderBy($orderBy[0], $orderBy[1]);
        }

        $builder->setFirstResult($start);
        $builder->setMaxResults($limit);

        return $builder->execute()->fetchAll() ?: array();
    }

    public function updateMembers($conditions, $updateFields)
    {
        return $this->db()->update($this->table, $updateFields, $conditions);
    }

    public function countThreadsByCourseIdAndUserId($courseId, $userId, $type = 'discuss')
    {
        $sql = "SELECT count(id) FROM course_thread WHERE type='{$type}' AND courseId = ? AND userId = ?";
        return $this->db()->fetchColumn($sql, array($courseId, $userId));
    }

    public function countActivitiesByCourseIdAndUserId($courseId, $userId)
    {
        $sql = "SELECT count(distinct(activityId)) FROM course_task_result WHERE courseId = ? AND userId = ?";
        return $this->db()->fetchColumn($sql, array($courseId, $userId));
    }

    public function countPostsByCourseIdAndUserId($courseId, $userId)
    {
        $sql = "SELECT count(id) FROM course_thread_post WHERE userId = ? and threadId IN (SELECT id FROM course_thread WHERE courseId = ? AND type='discussion')";
        return $this->db()->fetchColumn($sql, array($userId, $courseId));
    }

    protected function _buildJoinQueryBuilder($conditions, $joinConnections = '')
    {
        $conditions = array_filter($conditions, function ($value) {
            if ($value === '' || $value === null) {
                return false;
            }
            return true;
        });

        $builder = new DynamicQueryBuilder($this->db(), $conditions);
        $builder->from($this->table(), 'm')
            ->join('m', 'c2_course', 'c', 'm.courseId = c.id '.$joinConnections)
            ->andWhere('m.isLearned = :isLearned')
            ->andWhere('m.userId = :userId')
            ->andWhere('m.role = :role')
            ->andWhere('m.courseId = :courseId')
            ->andWhere('m.joinedType =:joinedType')
            ->andWhere('m.noteNum > :noteNumGreaterThan')
            ->andWhere('c.type = :type')
            ->andWhere('c.parentId = :parentId')
            ->andWhere('c.serializeMode =  :serializeMode')
            ->andWhere('c.serializeMode IN ( :serializeModes)');

        return $builder;
    }

    public function declares()
    {
        return array(
            'timestamps' => array('createdTime', 'updatedTime'),
            'orderbys'   => array('createdTime'),
            'conditions' => array(
                'userId = :userId',
                'courseId = :courseId',
                'isLearned = :isLearned',
                'joinedType = :joinedType',
                'role = :role',
                'classroomId = :classroomId',
                'noteNum > :noteNumGreaterThan',
                'createdTime >= :startTimeGreaterThan',
                'createdTime < :startTimeLessThan',
                'courseId IN (:courseIds)',
                'userId IN (:userIds)',
                'learnedNum >= :learnedNumGreaterThan',
                'learnedNum < :learnedNumLessThan',
                'deadline >= :deadlineGreaterThan'
            )
        );
    }
}
