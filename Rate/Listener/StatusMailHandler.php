<?php
namespace Rate\Listener;

use Tk\ConfigTrait;
use Tk\Event\Subscriber;


/**
 * @author Michael Mifsud <info@tropotek.com>
 * @see http://www.tropotek.com/
 * @license Copyright 2015 Michael Mifsud
 */
class StatusMailHandler implements Subscriber
{
    use ConfigTrait;

    /**
     * @param \Bs\Event\StatusEvent $event
     * @throws \Exception
     */
    public function onSendStatusMessage(\Bs\Event\StatusEvent $event)
    {
        // do not send messages
        $course = \Uni\Util\Status::getCourse($event->getStatus());
        if (!$event->getStatus()->isNotify() || ($course && !$course->getCourseProfile()->isNotifications())) {
            \Tk\Log::debug('Skill::onSendAllStatusMessages: Status Notification Disabled');
            return;
        }
        $subject = \Uni\Util\Status::getSubject($event->getStatus());

        /** @var \Tk\Mail\CurlyMessage $message */
        foreach ($event->getMessageList() as $message) {
            if (!\Rate\Plugin::getInstance()->isCourseActive($message->get('course::id'))) return;

            /** @var \App\Db\Company $company */
            $company = \App\Db\CompanyMap::create()->find($message->get('company::id'));
            if(!$company) continue;

            $value = (float)\Rate\Db\ValueMap::create()->findAverage(array('companyId' => $company->getId()));
            $message->set('company::starRating', $value);

            $questionRatings = '';
            $questionList = \Rate\Db\QuestionMap::create()->findFiltered(array('courseId' => $message->get('course::id')));
            foreach ($questionList as $q) {
                $ratingsList = \Rate\Db\ValueMap::create()->findFiltered(array('companyId' => $company->id, 'questionId' => $q->getId()));
                $cnt = $ratingsList->count();
                $tot = 0;
                $rating = '';

                if ($cnt) {
                    foreach ($ratingsList as $v) {
                        $tot += (int)$v->value;
                    }
                    $r = round($tot / $cnt, 2);
                    $rating = sprintf('%.2f', $r);
                }

                $questionRatings .= sprintf('<li>%s [Rating: %s / 5.00]</li>', $q->text, $rating);
            }
            if ($questionRatings) {
                $questionRatings = '<ol>' . $questionRatings . '</ol>';
            }
            $message->set('company::questionRatings', $questionRatings);
        }
    }

    /**
     * @param \App\Event\PlacementReportEvent $event
     * @throws \Exception
     */
    public function onCommentReport(\App\Event\PlacementReportEvent $event)
    {
        $report = $event->getPlacementReport();
        if (!$report->getPlacement() || !\Rate\Plugin::getInstance()->isCourseActive($report->getPlacement()->getSubject()->getCourseId())) return;
        $val = \Rate\Db\Value::getCompanyRating($report->getPlacement()->getCompanyId(), $report->getPlacementId());
        if ($val !== null) {
            $html = sprintf('<p><small>Rating: %.2f / 5.00</small></p>', $val);
            $event->set('postHtml', $html);
        }
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return array(
            \Bs\StatusEvents::STATUS_CHANGE => array('onSendStatusMessage', 0),
            \App\AppEvents::COMPANY_COMMENT_REPORT => array('onCommentReport', 0)
        );
    }

}


