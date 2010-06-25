<?php
/**
 * @version $Id$
 * @copyright Center for History and New Media, 2007-2010
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Omeka
 **/

/**
 * All URLs for files are routed through this controller.
 *
 * @package Omeka
 * @subpackage Controllers
 * @author CHNM
 * @copyright Center for History and New Media, 2007-2010
 **/
class FilesController extends Omeka_Controller_Action
{
    public $contexts = array(
        'show' => array('omeka-xml', 'omeka-json')
    );
    
    public function init()
    {
        $this->_helper->db->setDefaultModelName('File');
        $this->checkUserPermissions();
    }
    
    protected function checkUserPermissions()
    {
        if (!$this->_getParam('id')) {
            $this->_helper->redirector->goto('browse', 'items');
        }
        $file = $this->findById(null, 'File');
        $user = $this->getCurrentUser();
        
        $action = $this->_request->getActionName();
        //Check 'edit' action.
        if (in_array($action, array('edit'))) {
            // Allow access for users who originally created the item.
            if ($file->getItem()->wasAddedBy($user)) {
                $this->_helper->acl->setAllowed($action);
            }
        }
    }
    
    public function indexAction()
    {
        $this->redirect->gotoUrl('');
    }
    
    // Should not browse files by themselves
    public function browseAction() {}
    
    public function addAction() {}
    
    public function showAction()
    {
        $file = $this->findById();
        
        Zend_Registry::set('file', $file);
        $this->view->assign(compact('file'));
    }    
}