<?php
namespace AppBundle\Controller\Classroom;

use Topxia\Common\Paginator;
use Topxia\Common\ArrayToolkit;
use Biz\Taxonomy\Service\TagService;
use Biz\Course\Service\CourseService;
use Biz\Course\Service\MemberService;
use Biz\System\Service\SettingService;
use AppBundle\Controller\BaseController;
use Biz\Classroom\Service\ClassroomService;
use Symfony\Component\HttpFoundation\Request;
use Biz\Classroom\Service\ClassroomReviewService;

class CourseController extends BaseController
{
    public function pickAction($classroomId)
    {
        $this->getClassroomService()->tryManageClassroom($classroomId);
        $actviteCourses = $this->getClassroomService()->findActiveCoursesByClassroomId($classroomId);

        $excludeIds = ArrayToolkit::column($actviteCourses, 'parentId');
        $conditions = array(
            'status'     => 'published',
            'parentId'   => 0,
            'excludeIds' => $excludeIds
        );

        $paginator = new Paginator(
            $this->get('request'),
            $this->getCourseService()->searchCourseCount($conditions),
            5
        );

        $courses = $this->getCourseService()->searchCourses(
            $conditions,
            'latest',
            $paginator->getOffsetCount(),
            $paginator->getPerPageCount()
        );

        $users = $this->getUsers($courses);

        return $this->render("classroom-manage/course/course-pick-modal.html.twig", array(
            'users'       => $users,
            'courses'     => $courses,
            'classroomId' => $classroomId,
            'paginator'   => $paginator
        ));
    }

    public function listAction(Request $request, $classroomId)
    {
        $classroom = $this->getClassroomService()->getClassroom($classroomId);
        $previewAs = "";

        if (empty($classroom)) {
            throw $this->createNotFoundException();
        }

        $courses       = $this->getClassroomService()->findActiveCoursesByClassroomId($classroomId);
        $currentUser   = $this->getCurrentUser();
        $courseMembers = array();
        $teachers      = array();

        foreach ($courses as &$course) {
            $courseMembers[$course['id']] = $this->getCourseMemberService()->getCourseMember($course['id'], $currentUser['id']);

            $course['teachers']      = empty($course['teacherIds']) ? array() : $this->getUserService()->findUsersByIds($course['teacherIds']);
            $teachers[$course['id']] = $course['teachers'];
        }

        $user = $this->getCurrentUser();

        $member = $user['id'] ? $this->getClassroomService()->getClassroomMember($classroom['id'], $user['id']) : null;
        if (!$this->getClassroomService()->canLookClassroom($classroom['id'])) {
            $classroomName = $this->setting('classroom.name', '班级');
            return $this->createMessageResponse('info', "非常抱歉，您无权限访问该{$classroomName}，如有需要请联系客服", '', 3, $this->generateUrl('homepage'));
        }

        $canManageClassroom = $this->getClassroomService()->canManageClassroom($classroomId);
        if ($request->query->get('previewAs') && $canManageClassroom) {
            $previewAs = $request->query->get('previewAs');
        }

        $member = $this->previewAsMember($previewAs, $member, $classroom);

        $layout = 'classroom/layout.html.twig';
        if ($member && !$member["locked"]) {
            $layout = 'classroom/join-layout.html.twig';
        }
        if (!$classroom) {
            $classroomDescription = array();
        } else {
            $classroomDescription = $classroom['about'];
            $classroomDescription = strip_tags($classroomDescription, '');
            $classroomDescription = preg_replace("/ /", "", $classroomDescription);
        }
        return $this->render("classroom/course/list.html.twig", array(
            'classroom'            => $classroom,
            'member'               => $member,
            'teachers'             => $teachers,
            'courses'              => $courses,
            'courseMembers'        => $courseMembers,
            'layout'               => $layout,
            'classroomDescription' => $classroomDescription
        ));
    }

    public function searchAction(Request $request, $classroomId)
    {
        $this->getClassroomService()->tryManageClassroom($classroomId);
        $key = $request->request->get("key");

        $conditions             = array("title" => $key);
        $conditions['status']   = 'published';
        $conditions['parentId'] = 0;
        $paginator              = new Paginator(
            $this->get('request'),
            $this->getCourseService()->searchCourseCount($conditions),
            5
        );

        $courses = $this->getCourseService()->searchCourses(
            $conditions,
            'latest',
            $paginator->getOffsetCount(),
            $paginator->getPerPageCount()
        );

        $users = $this->getUsers($courses);

        return $this->render('TopxiaWebBundle:Course:course-select-list.html.twig', array(
            'users'       => $users,
            'courses'     => $courses,
            'paginator'   => $paginator,
            'classroomId' => $classroomId,
            'type'        => 'ajax_pagination'
        ));
    }

    protected function getUsers($courses)
    {
        $userIds = array();
        foreach ($courses as &$course) {
            $tags = $this->getTagService()->findTagsByOwner(array('ownerType' => 'course', 'ownerId' => $course['id']));

            $course['tags'] = ArrayToolkit::column($tags, 'id');
            $userIds        = array_merge($userIds, $course['teacherIds']);
        }

        return $this->getUserService()->findUsersByIds($userIds);
    }

    private function previewAsMember($previewAs, $member, $classroom)
    {
        $user = $this->getCurrentUser();

        if (in_array($previewAs, array('guest', 'auditor', 'member'))) {
            if ($previewAs == 'guest') {
                return array();
            }

            $member = array(
                'id'          => 0,
                'classroomId' => $classroom['id'],
                'userId'      => $user['id'],
                'orderId'     => 0,
                'levelId'     => 0,
                'noteNum'     => 0,
                'threadNum'   => 0,
                'remark'      => '',
                'role'        => array('auditor'),
                'locked'      => 0,
                'createdTime' => 0
            );

            if ($previewAs == 'member') {
                $member['role'] = array('member');
            }
        }

        return $member;
    }

    /**
     * @return CourseService
     */
    private function getCourseService()
    {
        return $this->createService('Course:CourseService');
    }

    /**
     * @return ClassroomService
     */
    private function getClassroomService()
    {
        return $this->createService('Classroom:ClassroomService');
    }

    /**
     * @return ClassroomReviewService
     */
    protected function getClassroomReviewService()
    {
        return $this->createService('Classroom:ClassroomReviewService');
    }

    /**
     * @return TagService
     */
    private function getTagService()
    {
        return $this->createService('Taxonomy:TagService');
    }

    /**
     * @return SettingService
     */
    protected function getSettingService()
    {
        return $this->createService('System:SettingService');
    }

    /**
     * @return MemberService
     */
    protected function getCourseMemberService()
    {
        return $this->createService('Course:MemberService');
    }
}