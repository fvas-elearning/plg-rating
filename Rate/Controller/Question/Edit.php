<?php
namespace Rate\Controller\Question;

use Dom\Template;
use Tk\Form\Event;
use Tk\Form\Field;
use Tk\Request;


/**
 * @author Michael Mifsud <info@tropotek.com>
 * @see http://www.tropotek.com/
 * @license Copyright 2015 Michael Mifsud
 */
class Edit extends \App\Controller\AdminEditIface
{

    /**
     * @var \Rate\Db\Question
     */
    protected $question = null;



    /**
     * Iface constructor.
     */
    public function __construct()
    {
        $this->setPageTitle('Rating Question Edit');
    }

    /**
     *
     * @param Request $request
     * @throws \Exception
     * @throws \Tk\Db\Exception
     * @throws \Tk\Form\Exception
     */
    public function doDefault(Request $request)
    {
        $this->question = new \Rate\Db\Question();
        $this->question->profileId = (int)$request->get('profileId');
        if ($request->get('questionId')) {
            $this->question = \Rate\Db\QuestionMap::create()->find($request->get('questionId'));
        }

        $this->buildForm();

        $this->form->load(\Rate\Db\QuestionMap::create()->unmapForm($this->question));
        $this->form->execute($request);
    }

    /**
     * @throws \Tk\Db\Exception
     * @throws \Tk\Exception
     * @throws \Tk\Form\Exception
     */
    protected function buildForm() 
    {
        $this->form = \App\Config::getInstance()->createForm('ratingQuestionEdit');
        $this->form->setRenderer(\App\Config::getInstance()->createFormRenderer($this->form));

        $this->form->addField(new Field\Input('text'));
        $this->form->addField(new Field\Input('help'));
        $this->form->addField(new Field\Checkbox('total'))->setNotes('Add this questions values to the ' .
            \App\Db\Phrase::findValue('company', $this->question->profileId) . ' total rating calculations.');

        $this->form->addField(new Event\Submit('update', array($this, 'doSubmit')));
        $this->form->addField(new Event\Submit('save', array($this, 'doSubmit')));
        $this->form->addField(new Event\Link('cancel', $this->getConfig()->getBackUrl()));

    }

    /**
     * @param \Tk\Form $form
     * @param \Tk\Form\Event\Iface $event
     * @throws \ReflectionException
     * @throws \Tk\Db\Exception
     * @throws \Tk\Exception
     */
    public function doSubmit($form, $event)
    {
        // Load the object with data from the form using a helper object
        \Rate\Db\QuestionMap::create()->mapForm($form->getValues(), $this->question);

        $form->addFieldErrors($this->question->validate());

        if ($form->hasErrors()) {
            return;
        }
        $this->question->save();

        \Tk\Alert::addSuccess('Record saved!');
        $event->setRedirect($this->getConfig()->getBackUrl());
        if ($form->getTriggeredEvent()->getName() == 'save') {
            $event->setRedirect(\Tk\Uri::create()->set('questionId', $this->question->getId()));
        }
    }

    /**
     * @return \Dom\Template
     */
    public function show()
    {
        $template = parent::show();

        // Render the form
        $template->insertTemplate('form', $this->form->getRenderer()->show());

        return $template;
    }

    /**
     * DomTemplate magic method
     *
     * @return Template
     */
    public function __makeTemplate()
    {
        $xhtml = <<<HTML
<div>
    
  <div class="panel panel-default">
    <div class="panel-heading">
      <h4 class="panel-title"><i class="fa fa-star"></i> <span var="panel-title">Rating Question Edit</span></h4>
    </div>
    <div class="panel-body">
      <div var="form"></div>
    </div>
  </div>
  
</div>
HTML;

        return \Dom\Loader::load($xhtml);
    }

}