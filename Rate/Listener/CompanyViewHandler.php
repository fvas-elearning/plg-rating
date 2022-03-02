<?php
namespace Rate\Listener;

use Rate\Plugin;
use Tk\Event\Subscriber;

/**
 * @author Michael Mifsud <info@tropotek.com>
 * @see http://www.tropotek.com/
 * @license Copyright 2015 Michael Mifsud
 */
class CompanyViewHandler implements Subscriber
{

    /**
     * @var \App\Controller\Placement\ReportEdit
     */
    private $controller = null;


    /**
     * Check the user has access to this controller
     *
     * @param \Tk\Event\Event $event
     * @throws \Exception
     */
    public function onControllerInit(\Tk\Event\Event $event)
    {
        /** @var \Tk\Controller\Iface $controller */
        $this->controller = $event->get('controller');
        if ($this->controller instanceof \App\Controller\Company\View || $this->controller instanceof \App\Controller\Company\CommentReport) {
            if (!\Rate\Plugin::getInstance()->isCourseActive($this->controller->getCourse()->getId())) return;
            if ($this->controller->getAuthUser()->isStaff() || $this->controller->getAuthUser()->isStudent()) {
                $template = $this->controller->getTemplate();
                $template->appendCssUrl(\Tk\Uri::create(Plugin::getInstance()->getPluginPath().'/assets/rating.less'));

                // TODO: Make these a configurable option in the profile plugin settings
                $this->showCompanyRatings($this->controller);
                $this->showCompanyRatingTotal($this->controller);
                $this->showCommentsRating($this->controller);
            }
        }
    }

    /**
     * @param \App\Controller\Company\View $controller
     */
    protected function showCommentsRating($controller)
    {
        /** @var \App\Table\CompanyComments $commentTable */
        $commentTable = $controller->getCommentTable();
        if ($commentTable) {
            $commentTable->prependCell(new \Tk\Table\Cell\Text('rating'), 'title')
                ->addOnCellHtml(
                    function ($cell, $obj, $html) {
                        /** @var \Tk\Table\Cell\Iface $cell */
                        /** @var \App\Db\PlacementReport $obj */
                        $cell->addCss('pull-right');
                        $value = '';
                        if ($obj->getPlacement()) {
                            $value = (float)\Rate\Db\ValueMap::create()->findAverage(array('companyId' => $obj->getPlacement()->companyId, 'placementId' => $obj->placementId));
                        }
                        if (!$value) return '';
                        return sprintf('<div class="rate-star-rating"><em>%s</em><br/>%s</div>',
                            \App\Db\Phrase::findValue('star-rating', $obj->getPlacement()->getSubject()->getCourseId()),
                            \Rate\Ui\Stars::create($value, true));
                    }
                );
        }
    }


    /**
     * @param \App\Controller\Company\View $controller
     * @throws \Exception
     */
    protected function showCompanyRatings($controller)
    {
        $template = $controller->getTemplate();
        $company = $controller->getCompany();

        // Individual rating question list
        $questionList = \Rate\Db\QuestionMap::create()->findFiltered(array('courseId' => $company->courseId));
        $html = '';
        foreach ($questionList as $question) {
            $value = (float)\Rate\Db\ValueMap::create()->findAverage(array('companyId' => $company->getId(), 'questionId' => $question->getId()));
            $html .= sprintf('<li class="rating-question-value"><div class="pull-right">%s</div><span>%s</span></li>',
                \Rate\Ui\Stars::create($value, true), $question->text);
        }
        if ($html) {
            $tpl = <<<HTML
<section class="companyRating">
  <h4 class="content-title">%s</h4>
  <ol class="star-rating-list">
    %s
  </ol>
</section>
HTML;
            $html = sprintf($tpl, \App\Db\Phrase::findValue('star-rating', $company->courseId), $html);
            $template->appendHtml('right-col', $html);
        }
    }

    /**
     * @param \App\Controller\Company\View $controller
     * @throws \Exception
     */
    protected function showCompanyRatingTotal($controller)
    {
        $template = $controller->getTemplate();
        $company = $controller->getCompany();

        // Company Profile Total
        $value = (float)\Rate\Db\ValueMap::create()->findAverage(array('companyId' => $company->getId()));

//        $html = sprintf('<div class="rate-star-rating pull-right text-center"><em>%s</em><br/>%s</div>',
//            \App\Db\Phrase::findValue('company-view-star-rating', $company->profileId), \Rate\Ui\Stars::create($value, true));

        $html = sprintf('<div class="rate-star-rating pull-right text-center" title="%s">%s</div>',
            htmlentities(\App\Db\Phrase::findValue('company-view-star-rating', $company->courseId)), \Rate\Ui\Stars::create($value, true));

        $template->appendHtml('top-col-right', $html);
    }




    /**
     * Check the user has access to this controller
     *
     * @param \Tk\Event\Event $event
     */
    public function onControllerShow(\Tk\Event\Event $event) { }


    /**
     * @return array The event names to listen to
     * @api
     */
    public static function getSubscribedEvents()
    {
        return array(
            \Tk\PageEvents::CONTROLLER_INIT => array('onControllerInit', 0),
            \Tk\PageEvents::CONTROLLER_SHOW => array('onControllerShow', 0)
        );
    }

}