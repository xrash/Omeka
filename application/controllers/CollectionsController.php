<?php
/**
 * @version $Id$
 * @copyright Center for History and New Media, 2007-2010
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Omeka
 **/

/**
 * CRUD controller for Collections
 *
 * @package Omeka
 * @subpackage Controllers
 * @author CHNM
 * @copyright Center for History and New Media, 2007-2010
 **/
class CollectionsController extends Omeka_Controller_Action
{
    protected $_browseRecordsPerPage = 10;
    
    public $contexts = array(
        'remove-collector' => array('json')
    );
    
    public function init()
    {
        $this->_helper->db->setDefaultModelName('Collection');     
    }
    
    protected function _getAddSuccessMessage($record)
    {
        $collection = $record;
        return 'The collection "' . $collection->name . '" was successfully added!';        
    }
    
    protected function _getEditSuccessMessage($record)
    {
        $collection = $record;
        return 'The collection "' . $collection->name . '" was successfully changed!';        
    }
    
    protected function _getDeleteSuccessMessage($record)
    {
        $collection = $record;
        return 'The collection "' . $collection->name . '" was successfully deleted!';        
    }
    
    /**
     * Ajax Action for removing collectors that are listed under collections.
     * 
     * This will either render a JSON from the Ajax request or, if Javascript
     * is not enabled, it will redirect to the 'show' page for the collection.
     * 
     * @return void
     **/
    public function removeCollectorAction()
    {
        $collection = $this->findById($this->_getParam('collection_id'));
        $collector  = $this->findById($this->_getParam('collector_id'), 'Entity');
                
        $this->view->result = $collection->removeCollector($collector);
                
        //If the request is not done through AJAX, redirect
        if (!$this->getRequest()->isXmlHttpRequest()) {
            $this->redirect->goto('edit', null, 
                                  null, array('id' => $collection->id));
        }
    }
}