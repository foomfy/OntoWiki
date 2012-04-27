<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2012, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

/**
 * The history plugin is used on different events
 *
 * @category   OntoWiki
 * @package    OntoWiki_extensions_history
 * @author     Sebastian Tramp <mail@sebastian.tramp.name>
 * @author     Florian Agsten
 * @author     Christoph Riess
 */
class HistoryPlugin extends OntoWiki_Plugin
{
    
    public function onPropertiesAction($event){
        $translate = OntoWiki::getInstance()->translate;
        $owApp = OntoWiki::getInstance();
        
        $menu = OntoWiki_Menu_Registry::getInstance()->getMenu('resource');
        $menu->appendEntry(OntoWiki_Menu::SEPARATOR);
        $menu->appendEntry($translate->_('Subscribe for Resource Feed'), array('url' => $owApp->getUrlBase().'history/subscribe?r='.urlencode($event->uri)));
    }

    public function onAddStatement(Erfurt_Event $event)
    {
        $this->_log("histories onAddStatement");
    }

    public function onAddMultipleStatements(Erfurt_Event $event)
    {
        $this->_log("histories onAddMultipleStatements");
        $urlBase = OntoWiki::getInstance()->getUrlBase();
        $subEvent = new Erfurt_Event('onInternalFeedDidChange');
        foreach($event->statements as $resource => $content){
            require_once 'HistoryController.php';
            $subEvent->feedUrl = HistoryController::getFeedUrlStatic($urlBase, $resource, $this->_privateConfig->resourceParamName);
            $subEvent->trigger();
        }
    }

    public function onDeleteMatchingStatements(Erfurt_Event $event)
    {
        $this->_log("histories onDeleteMatchingStatements");
    }

    public function onDeleteMultipleStatements(Erfurt_Event $event)
    {
        $this->_log("histories onDeleteMultipleStatements");
    }
    
    public function onExternalFeedDidChange(Erfurt_Event $event){
        $this->_log('processing payload: ');
        
        try{
            // creates a feed object from the feed string
            $this->_privateConfig->__set('timeout', 50);
            $feed = new Zend_Feed_Atom(null, $event->feedData);
            
            // loads the resource 
            $tmp = explode($this->_privateConfig->resourceParamName.'=', $feed->link('self'));
            $resourceUri = urldecode($tmp[1]);
            
            // 1. Retrieve HTTP-Header and check if the x-syncfeed url is the same as the url from the feed
            $headers = get_headers($resourceUri, 1);
            if (!is_array($headers)) {
                return;
            }
            if (isset($headers['X-Syncfeed'])) {
                $this->_log('feed is syncfeed (historyfeed)');
            }
            else
                return;
        }
        catch (Exception $e){
            $this->_log($e->getMessage());
            $this->_log("in feed: ");
            $this->_log(print_r($event->feedData,true));
        }
    }

    /*
     * used to add a special http response header
     */
    public function onBeforeLinkedDataRedirect($event)
    {
        if ($event->response === null) {
            return;
        }

        $url      = $this->_getSyncFeed((string) $event->resource);
        $response = $event->response;
        $response->setHeader('X-Syncfeed', $url, true);
    }

    /*
     * used on export event to add a statement to the export
     */
    public function beforeExportResource($event)
    {
        // this is the event value if there are other plugins before
        $prevModel = $event->getValue();
        // throw away non memory model values OR use the given one if valid
        if (is_object($prevModel) && get_class($prevModel) == 'Erfurt_Rdf_MemoryModel') {
            $newModel = $prevModel;
        } else {
            $newModel = new Erfurt_Rdf_MemoryModel();
        }

        // prepare the statement URIs
        $subjectUri  = (string) $event->resource;
        $propertyUri = $this->_privateConfig->syncfeedProperty;
        $objectUri   = $this->_getSyncFeed((string) $subjectUri);

        $newModel->addRelation(
            $subjectUri, $propertyUri, $objectUri
        );

        return $newModel;
    }

    /*
     * get a feed URL for a resource URI
     */
    private function _getSyncFeed($resource)
    {
        $owApp = OntoWiki::getInstance();
        return $owApp->config->urlBase . 'history/feed?'.$this->_privateConfig->resourceParamName.'=' . urlencode($resource);
    }

    private function _log($msg)
    {
        $logger = OntoWiki::getInstance()->getCustomLogger($this->_privateConfig->logname);
        $logger->debug($msg);
    }
}
