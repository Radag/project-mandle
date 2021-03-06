<?php

namespace App\FrontModule\Components\Stream\MessageForm;

use \Nette\Application\UI\Form;
use App\Model\Manager\MessageManager;
use App\Model\Manager\UserManager;
use App\Model\Manager\FileManager;
use App\Model\Entities\Message;
use App\Model\Manager\TaskManager;
use App\Model\Manager\ClassificationManager;
use App\Model\Manager\GroupManager;
use Dusterio\LinkPreview\Client;
use App\Service\FileService;

class MessageForm extends \App\Components\BaseComponent
{ 
    /** @var MessageManager */
    protected $messageManager;
    
    /** @var UserManager */
    protected $userManager;
    
    /** @var FileManager $fileManager */
    protected $fileManager;
    
    /** @var TaskManager $taskManager*/
    protected $taskManager;
    
    /** @var ClassificationManager **/
    protected $classificationManager;
    
     /** @var GroupManager **/
    protected $groupManager;
    
    /** @var FileService **/
    protected $fileService;
    
    /** @var Message */
    protected $defaultMessage = null;   
    
    public function __construct(UserManager $userManager,
        MessageManager $messageManager, 
        FileManager $fileManager,
        TaskManager $taskManager,
        ClassificationManager $classificationManager,
        GroupManager $groupManager,
        FileService $fileService
    )
    {
        $this->userManager = $userManager;
        $this->messageManager = $messageManager;
        $this->fileManager = $fileManager;
        $this->taskManager = $taskManager;
        $this->classificationManager = $classificationManager;
        $this->groupManager = $groupManager;
        $this->fileService = $fileService;
    }
  
    public function createComponentForm()
    {   
        $form = $this->getForm(false);
        $form->getElementPrototype()->class('ajax');
        $form->addTextArea('text', 'Zpráva')
            ->setRequired('Napište zprávu');
        $form->addHidden('idMessage');
        $form->addHidden('messageType', Message::TYPE_NOTICE);
        $form->addText('title', 'Název')
             ->addConditionOn($form['messageType'], Form::EQUAL, Message::TYPE_TASK)
                 ->setRequired('Vložte název');
        $form->addText('date', 'Datum', null, 12)
             ->setAttribute('type', 'date')
             ->setAttribute('placeholder', date('d. m. Y'))
             ->setValue(date("Y-m-d"))
             ->addConditionOn($form['messageType'], Form::EQUAL, Message::TYPE_TASK)
                 ->setRequired('Vložte datum')
                 ->addRule(Form::PATTERN, 'Datum musí být ve formátu 2011-10-15', '([0-9]{4})\-([0-9]{2})\-([0-9]{2})');
        $form->addText('time', 'Čas', null, 5)
             ->setAttribute('placeholder', date('H:i'))
             ->setAttribute('type', 'time')
             ->setValue(date('H:i'))
             ->addConditionOn($form['messageType'], Form::EQUAL, Message::TYPE_TASK)
                 ->setRequired('Vložte čas')
                 ->addRule(Form::PATTERN, 'Čas musí být ve formátu 12:45', '([0-9]{2})\:([0-9]{2})');
        $form->addCheckbox('online', "Studenti odevzdají práci online");
        $form->addCheckbox('student_list', "Vytvořit prezenční listinu");
        $form->addCheckbox('create_classification', "Povinnost bude známkována");
        $form->addHidden('attachments');
        $form->addHidden('delete_attachments');
        $form->addSubmit('send', 'Publikovat');
        if($this->defaultMessage !== null) {
            $form->setDefaults(array(
                'text' => $this->defaultMessage->text,
                'idMessage' => $this->defaultMessage->id,
                'messageType' => $this->defaultMessage->type,
                'title' => $this->defaultMessage->title
            ));
            if($this->defaultMessage->task) {
                $form->setDefaults(array(
                    'title' => $this->defaultMessage->task->title,
                    'date' => $this->defaultMessage->task->deadline->format('Y-m-d'),
                    'time' => $this->defaultMessage->task->deadline->format('H:i'),
                    'online' => $this->defaultMessage->task->online,
                    'create_classification' => $this->defaultMessage->task->createClassification
                )); 
            }
        }
        $form->onError[] = function(Form $form) {
            $this->template->links = $this->loadLinks();
            $attachments = [];
            foreach(explode('_', $form->getValues()['attachments']) as $attach) {
                if($attach) {
                    $attachments[] = $this->fileManager->getFile($attach);
                }
            }
            $this->template->attachments = $attachments;
        };

        $form->onSuccess[] = [$this, 'processForm'];
          
        $form->onValidate[] = function($form) {
            if($this->presenter->activeGroup->relation === 'owner') {
                $allowed = [Message::TYPE_MATERIALS, Message::TYPE_NOTICE, Message::TYPE_TASK];
            } else {
                $allowed = [ Message::TYPE_NOTICE];
            }            
            if(!in_array($form['messageType']->getValue(), $allowed)) {
                $form->addError('Takový typ nelze zadat.');
            }
        };
        return $form;
    }    
    
    
    public function processForm(Form $form, $values) 
    {
        $message = new \App\Model\Entities\Message;
        $message->text = $values->text;
        $message->user = $this->presenter->activeUser;
        $message->idGroup = $this->presenter->activeGroup->id;
        $message->type = $values->messageType;
        
        if(!empty($values->idMessage)) {            
            $message->id = $values->idMessage;
            if(!$this->messageManager->canUserEditMessage($message->id, $this->presenter->activeUser, $this->presenter->activeGroup)) {
                throw new \InvalidArgumentException('Wrong ID');
            }
        }
        
        $attachments = explode('_', $values->attachments);

        $deleteAttachments = explode('_', $values->delete_attachments);
        if($deleteAttachments) {
            foreach($deleteAttachments as $idFile) {
                if($idFile && $this->fileManager->isFileOwner($idFile, $this->presenter->activeUser->id)) {
                    $this->fileService->removeFile($idFile);
                }
            }
        }
        
        $message->id = $this->messageManager->createMessage($message, $attachments, $this->presenter->activeGroup);
        
        if($values->messageType === Message::TYPE_TASK) {
            $task = new \App\Model\Entities\Task();
            $task->idMessage = $message->id;
            $task->online = $values->online ? 1 : 0;
            $task->title = $values->title;
            $deadline = $date = \DateTime::createFromFormat('Y-m-d H:i', $values->date . " " . $values->time);
            $task->deadline = $deadline;
            $task->create_classification = $values->create_classification;
            $task->id = $this->taskManager->createTask($task);
            $this->classificationManager->updateTaskClassification($task, $this->presenter->activeGroup, $this->groupManager);
        }
                
        if($values->messageType === Message::TYPE_MATERIALS) {
            $message->title = $values->title;
            $this->messageManager->createMaterial($message);
        }
        
        $youtube = $this->presenter->request->getPost('link_youtube');        
        $webs = $this->presenter->request->getPost('link_web');
        if(!empty($youtube)) {
            foreach($youtube as $link) {
                $this->messageManager->addMessageLink((object)[
                    'youtube' => $link,
                    'web' => null,
                    'message_id' => $message->id,
                    'title' => null,
                    'image' => null,
                    'description' => null
                ]);
            }
        }        
        if(!empty($webs)) {
            foreach($webs as $web) {
                $webData = $this->transformWeb($web);                
                $this->messageManager->addMessageLink((object)[
                    'youtube' => null,
                    'web' => $webData->url,
                    'message_id' => $message->id,
                    'title' => $webData->title,
                    'image' => isset($webData->image) ? $webData->image : null,
                    'description' => isset($webData->description) ? $webData->description : null
                ]);
            }
        }        

        $this->presenter->flashMessage('Zpráva uložena', 'success');
        $this->presenter->payload->idMessage = $message->id;
        $this->handleResetForm();
        $this->parent->redrawControl('streamSection');
    }
    
    public function handleResetForm($type = Message::TYPE_NOTICE) {
        
        $this->template->attachments = $this->fileManager->getUnusedUserFiles(FileService::FILE_PURPOSE_STREAM);
        $ids = [];
        if(is_array($this->template->attachments)) {
            foreach($this->template->attachments as $att) {
                $ids[] = $att->id;
            }
        }
        $this['form']->setValues([
            'messageType' => $type,
            'time' => date("H:i"),
            'date' => date("Y-m-d"),
            'attachments' => implode('_', $ids)
        ], true);
    }
    
    
    public function loadLinks()
    {
        $links = [];
        $youtube = $this->presenter->request->getPost('link_youtube');        
        $webs = $this->presenter->request->getPost('link_web');
        if(!empty($youtube)) {
            foreach($youtube as $link) {
               $links[] = (object)['youtube' => $link]; 
            }
        }        
        if(!empty($webs)) {
            foreach($webs as $web) {
               $links[] = (object)['web' => $this->transformWeb($web)]; 
            }
        }
        return $links;
    }
    
    public function handleAddLink()
    {
        $links = $this->loadLinks();
        $link = $this->presenter->request->getPost('link');
        if(mb_strrpos($link, 'http://') === false && mb_strrpos($link, 'https://') === false) {
            $link = 'http://' . $link;
        }
        $url = parse_url($link);
        if(isset($url['host'])) {
            if($url['host'] === 'www.youtube.com' && isset($url['path']) && $url['path'] === '/watch') {
                $output = [];
                parse_str($url['query'], $output); 
                $links[] = (object)['youtube' => $output['v']];
            } else {
                $links[] = (object)['web' => $this->transformWeb($link, $url)];
            }     
            $this->presenter->payload->invalidForm = false;
            $this->template->links = $links;
            $this->redrawControl('link-section');
            $this->redrawControl('link-snippet');
        } else {
            $this->template->linkErrors = ['Špatné url'];
            $this->presenter->payload->invalidForm = true;
            $this->redrawControl('errors');
        }              
        
        
    }
    
    protected function transformWeb($web, $url = null)
    {
//        $page = new EmbedPage($web);
//        return (object)[
//            'image' => $page->getImage(),
//            'title' => $page->getTitle(),
//            'description' => $page->getDescription(),
//            'url' => $web
//        ];
        
        try {
            $page = new Client($web);
            $previews = $page->getPreview('general')->toArray();
        } catch (\Exception $ex) {
            return (object)['url' => $web, 'title' => $web];
        }
        
        $title = $description = $image = null;
        if($previews['description']) {
            $description = $previews['description'];
        }
        if(isset($previews['cover'])) {
            $image = $previews['cover'];       
            if(mb_strrpos($image, 'http://') === false && mb_strrpos($image, 'https://') === false) {
                $image = $url['scheme'] . '://' . $url['host'] . $image;
            }
        }
        if(isset($previews['title'])) {
            $title = $previews['title'];
        }
        
        return (object)[
            'image' => $image,
            'title' =>  $title,
            'description' => $description,
            'url' => $web
        ];
    }
    
    public function render()
    {
        if($this->defaultMessage !== null) {
            if($this->defaultMessage->attachments) {
                $this->template->attachments = array_merge($this->defaultMessage->attachments['files'], $this->defaultMessage->attachments['media']);
            }            
            $this->template->submitButtonName = 'Upravit';
        } else {
            $this->template->submitButtonName = 'Publikovat';
        }
        $this->template->isOnwer = ($this->presenter->activeGroup->relation === 'owner');
        if($this->defaultMessage) {
            $this->template->messageUser = $this->defaultMessage->user;            
        } else {
            $this->template->messageUser = $this->presenter->activeUser;            
        }
        $this->template->defaultMessage = $this->defaultMessage;
        parent::render();
    }
    
    public function setDefaults(Message $message)
    {
        $this->defaultMessage = $message;
    }
    
    public function handleUploadAttachment()
    {
        $file = $this->presenter->request->getFiles();
        try {
            if(empty($file['file'])) { 
                throw new \Lato\FileUploadException("Soubor se nepodařilo nahrát.");
            }
            $this->presenter->payload->file = $this->fileService->uploadFile($file['file'], FileService::FILE_PURPOSE_STREAM);
        } catch (\Lato\FileUploadException $ex) {
            $this->presenter->payload->error = $ex->getMessage();
        }
        $this->presenter->sendPayload();
    }
    
    public function handleDeleteAttachment($idFile)
    {
        if($this->fileManager->isFileOwner($idFile, $this->presenter->activeUser->id)) {
            $this->fileService->removeFile($idFile);
            $this->getPresenter()->payload->id = $idFile;
            $this->getPresenter()->payload->deleted = true;
        } else {
            $this->getPresenter()->payload->deleted = false;
        }        
        $this->getPresenter()->sendPayload();
    }
}
