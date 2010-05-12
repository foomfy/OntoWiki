<?php
/**
 * Controller for OntoWiki Pattern Manager Component
 *
 * @category   OntoWiki
 * @package    extensions_components_patternmanager
 * @author     Christoph Rieß <c.riess.dev@googlemail.com>
 * @copyright  Copyright (c) 2010, {@link http://aksw.org AKSW}
 * @license    http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

require_once 'classes/BasicPattern.php';
require_once 'classes/ComplexPattern.php';
require_once 'classes/PatternEngine.php';
require_once 'classes/PatternFunction.php';

class PatternmanagerController extends OntoWiki_Controller_Component {
    
    /**
     * 
     * @var unknown_type
     */
    private $_engine = null;

    /**
     * Component global init function
     */
    public function init() {
    
        parent::init();
        
        OntoWiki_Navigation::disableNavigation();
        
        $this->view->placeholder('main.window.title')->set('OntoWiki Evolution Pattern Engine');
        
        $this->view->headScript()->appendFile($this->_componentUrlBase . 'scripts/jquery.autocomplete.js');
        
        //$this->view->headLink()->appendStylesheet($this->_componentUrlBase .'css/jquery.autocomplete.css');
        
        $this->_engine = new PatternEngine();
        $this->_engine->setConfig($this->_privateConfig);
        $this->_engine->setBackend($this->_erfurt);
        
    }
    
    /**
     * Shows an index page as entry portal for evolution pattern management
     */
    public function indexAction () {
        
        $this->view->componentUrlBase = $this->_componentUrlBase;

        $this->view->url = array();

        $url = new OntoWiki_Url(array('controller' => 'patternmanager', 'action' => 'browse') );
        $this->view->url['browse'] = $url;
        
        $url = new OntoWiki_Url(array('controller' => 'patternmanager', 'action' => 'view') );
        $this->view->url['view'] = $url;
        
    }
    
    /**
     * 
     */
    public function execAction() {
        
        // javascript functions
        $this->view->headScript()->appendFile($this->_componentUrlBase . 'scripts/patternmanager-exec.js');
        
        if (defined('_OWDEBUG')) {
            $start = microtime(true);
        }
        
        $this->_engine->setDefaultGraph($this->_owApp->selectedModel);
        
        $complexPattern = $this->_engine->loadFromStoreAsRdf($this->_request->getParam('pattern'));

        $unboundVariables = $complexPattern->getVariables(false);

        $var = $this->getParam('var');

        if (!empty($var) && is_array($var)) {
            
            foreach ($var as $name => $value) {
                unset($unboundVariables[$name]);
                $complexPattern->bindVariable($name,$value);
            }
            
            $this->_engine->processPattern($complexPattern);
        }
        
        // measurement for debug
        if (defined('_OWDEBUG')) {
            $end = microtime(true);
            $this->view->microtime = sprintf('%.3f s',$end - $start);
        }

        $this->view->variables = $unboundVariables;
        
        // button to commit pattern
        $toolbar = $this->_owApp->toolbar;
        $toolbar->appendButton(
            OntoWiki_Toolbar::SUBMIT,
            array('name' => $this->_owApp->translate->_('savepattern'))
        );
        $this->view->placeholder('main.window.toolbar')->set($toolbar);
        
        $url = new OntoWiki_Url(array('controller' => 'patternmanager', 'action' => 'exec'));

        //$this->view->placeholder('main.window.title')->set($windowTitle);

        $this->view->formActionUrl = (string) $url;
        $this->view->formMethod    = 'post';
        //$this->view->formName      = 'instancelist';
        //$this->view->formName      = 'patternmanager-form';
        $this->view->formEncoding  = 'multipart/form-data';

    }

    /**
     *
     */
    public function browseAction() {

        $this->_engine->setDefaultGraph($this->_owApp->selectedModel);
        
        $list = $this->_engine->listFromStore();
        
        $this->view->data = $list;
        
        /*
        $store = $this->_erfurt->getStore();
        
        $configModel = $this->_privateConfig->configModel;
        $className   = $this->_privateConfig->className;
        $hasJson     = $this->_privateConfig->hasJson;
        
        $query = new Erfurt_Sparql_Query2();
        $query->addFrom($configModel);
        $query->addTriple(
            new Erfurt_Sparql_Query2_Var('pattern'),
            new Erfurt_Sparql_Query2_IriRef(EF_RDF_TYPE),
            new Erfurt_Sparql_Query2_IriRef($className)
        );
        $query->addTriple(
            new Erfurt_Sparql_Query2_Var('pattern'),
            new Erfurt_Sparql_Query2_IriRef(EF_RDFS_LABEL),
            new Erfurt_Sparql_Query2_Var('label')
        );
        
        $result  = array();
        $count   = 0;
        $nrArray = array();
        
        foreach ($store->sparqlQuery($query,array(STORE_USE_AC => false)) as $row) {
            
            if ( !array_key_exists($row['pattern'],$nrArray) ) {
                $result[++$count]['uri'] = $row['pattern'];
                $nrArray[$row['pattern']] = $count;
            }
            
            if ( $row['P'] === EF_RDFS_LABEL ) {
                $result[$nrArray[$row['pattern']]]['label'] = $row['O'];
            }

            $execUrl = new OntoWiki_Url(array('controller' => 'patternmanager','action' => 'exec'));
            $execUrl->setParam('pattern', $row['pattern']);
            $result[$nrArray[$row['pattern']]]['exec_url'] = $execUrl;
                
            $viewUrl = new OntoWiki_Url(array('controller' => 'patternmanager','action' => 'view'));
            $viewUrl->setParam('pattern', $row['pattern']);
            $result[$nrArray[$row['pattern']]]['view_url'] = $viewUrl;

        }
        
        $this->view->data = $result;
        */
        
    }
    
    /**
     *
     */
    public function viewAction() {
        
        // javascript functions
        $this->view->headScript()->appendFile($this->_componentUrlBase . 'scripts/patternmanager-view.js');
        
        // button to commit pattern
        $toolbar = $this->_owApp->toolbar;
        $toolbar->appendButton(
            OntoWiki_Toolbar::SUBMIT,
            array('name' => $this->_owApp->translate->_('savepattern'))
        );
        $this->view->placeholder('main.window.toolbar')->set($toolbar);
        
        $url = new OntoWiki_Url(array('controller' => 'patternmanager', 'action' => 'save'));

        //$this->view->placeholder('main.window.title')->set($windowTitle);

        $this->view->formActionUrl = (string) $url;
        $this->view->formMethod    = 'post';
        //$this->view->formName      = 'instancelist';
        //$this->view->formName      = 'patternmanager-form';
        $this->view->formEncoding  = 'multipart/form-data';
        
        $this->view->jsonPattern = '{}';
        
        $param = $this->_request->getParam('pattern', null);
        if ( !empty($param) ) {
            $loaded = $this->_engine->loadFromStore($param);
            $this->view->jsonPattern = $loaded->toArray(true);
        }
        
        $param = $this->_request->getParam('json_pattern', null);
        if ( !empty($param) ) {
            $this->view->jsonPattern = $param;
        }
        
        $param = $this->_request->getParam('error_pattern', null);
        if ( !empty($param) ) {
            $this->view->errorPattern = $param;
        }

    }
    
    /**
     *  This function takes GET/POST Parameters and tries to create a valid 
     *  evolution pattern from it, which should be made persistent for later
     *  use.
     */
    public function saveAction() {
        
        // load params from request
        $params = $this->_request->getParams();
        
        // parametername prefixes
        // all parameters are in form like prefix-i-j where i,j are numeric indices
        // parameter numeric values should be consecutive integers
        $paramPrefixes = array(
            'patterndesc' ,
            'patternlabel' ,
            'varname' ,
            'vartype' ,
            'vardesc' ,
            'selectpattern' ,
            'insertpattern' ,
            'deletepattern'
        );
        
        $plainData = array();

        // convert parameters into matrix i , prefix , j (index reordering)
        foreach ($params as $name => $value) {
            if (sizeof(explode('-', $name)) > 1) {
                
                $parts = explode('-',$name);
                // only add to matrix if
                if ( in_array($parts[0],$paramPrefixes) && !empty($parts[1]) ) {
                  switch (sizeof($parts)) {
                      case 2:
                          $plainData[(int)$parts[1]][$parts[0]][] = trim($value);
                          break;
                      case 3:
                          $plainData[(int) $parts[1]][$parts[0]][(int) $parts[2]] = trim($value);
                          break;
                      default :
                          // do nothing
                          break;
                  }
                }
            }
        }

        $orderedData = array();

        //init complex pattern instance
        $complexPattern = new ComplexPattern();
        $complexPattern->setEngine($this->_engine);
        
        $error = array();
        
        // traversing data structure to prepare JSON conversion
        foreach ($plainData as $pNr => $pattern) {
            
            $basicPattern = new BasicPattern();
            
            $variables = array();
            
            if ( array_key_exists('varname', $pattern) ) {
	            foreach ($pattern['varname'] as $i => $name) {
	                $variables[$i] = array(
	                	'name' => $name ,
	                	'type' => $pattern['vartype'][$i],
	                	'desc' => $pattern['vardesc'][$i]
	                );
	                // variable names should only be alphanumeric and extra '-' and '_'
	                if (preg_match('/^([A-Z]|[a-z]|[0-9]|[_-])+$/',$name) === 0 ) {
	                    $error[] = 'varname-' . $pNr . '-' . $i;
	                }
	            }
            }
            
            if ( array_key_exists('selectpattern', $pattern) ) {
                foreach ($pattern['selectpattern'] as $s => $select) {
                    $selects[$pNr] = $select;
                    if (preg_match ('/^\{(.+)\}$/',$select) === 0 )  {
                        $error[] = 'selectpattern-' . $pNr . '-' . $s;
                    }
                }
            } else {
                $selects   = array();
            }
            
            $updates = array();
            
            if ( array_key_exists('insertpattern', $pattern) ) {
                foreach ($pattern['insertpattern'] as $n => $pat) {
                    $updates['INSERT'][$n] = $pattern['insertpattern'][$n];
                    if (preg_match ('/^(\s*)(\S+)(\s+)(\S+)(\s+)(\S+)(\s*)$/',$pat) === 0) {
                        $error[] = 'insertpattern-' . $pNr . '-' . $n;
                    }
                }
                
            } else {
                $updates['INSERT'] = array();
            }
            
            if ( array_key_exists('deletepattern', $pattern) ) {
                foreach ($pattern['deletepattern'] as $n => $pat) {
                    $updates['DELETE'][$n] = $pattern['deletepattern'][$n];
                    if (preg_match ('/^(\s*)(\S+)(\s+)(\S+)(\s+)(\S+)(\s*)$/',$pat) === 0) {
                        $error[] = 'deletepattern-' . $pNr . '-' . $n;
                    }
                }
            } else {
                $updates['DELETE'] = array();
            }
            
            foreach ($selects as $select) {
                $basicPattern->setSelectQuery($select);
            }
            
            foreach ($updates['DELETE'] as $update) {
                $basicPattern->addUpdateQuery($update,'delete');
            }
            
            foreach ($updates['INSERT'] as $update) {
                $basicPattern->addUpdateQuery($update,'insert');
            }
            
            $description = empty($pattern['patterndesc'][0]) ?
    			'unspecified Basic Pattern ' . date(DateTime::ISO8601) :
                $pattern['patterndesc'][0];
                
            $label = '';
            
            if ( empty($pattern['patternlabel'][0]) ) {
                $error[] = 'patternlabel-' . $pNr;
            } else {
                $label = $pattern['patternlabel'][0];
            }
            
            $basicPattern->setLabel($label);
            $basicPattern->setDescription($description);
            
            foreach ($variables as $var) {
                $basicPattern->addVariable($var['name'], $var['type'], $var['desc']);
            }
            
            $complexPattern->appendElement($basicPattern);
            
        }

        $description = empty($params['desc']) ?
    		'unspecified Pattern ' . date(DateTime::ISO8601) :
            $params['desc'];
        
        $label = empty($params['label']) ?
    		'unspecified Pattern ' . date(DateTime::ISO8601) :
            $params['label'];
            
        $complexPattern->setLabel($label);
        $complexPattern->setDescription($description);
        
        if (empty($error)) {
            $this->_engine->saveToStore($complexPattern);
        } else {
            $json = $complexPattern->toArray(true);
            
            $url = new OntoWiki_Url(
                array(
                    'controller' => 'patternmanager',
                	'action' => 'view'
                ),
                array()
            );
            $url->setParam('action','view');
            $url->setParam('json_pattern', $json);
            $url->setParam('error_pattern', json_encode($error));
            
            $this->_redirect($url);
        }
    }
    
    /**
     * 
     */
    public function autocompleteAction() {
        
        $this->_helper->viewRenderer->setNoRender();
        $this->_helper->layout->disableLayout();
        
        $model = $this->_owApp->selectedModel;

        $query = $this->_request->getParam('q','');
        $vartype = $this->_request->getParam('vartype');
        $limit = (int) $this->_request->getParam('limit',10);
        
        
        $allowedInputType = array(
            'RESOURCE' => '/([a-z]|[0-9]|[A-Z])+/',
            'LITERAL' => '/\S+/',
            'BasicPattern' => '/.*/'
        );
        
        $sparqlQuery = 'SELECT DISTINCT ?entity ' . PHP_EOL . 'FROM <' . (string) $model . '> WHERE ' . PHP_EOL;
        
        $error = false;
        $ret = array();
        
        if ( array_key_exists($vartype,$allowedInputType) ) {
        
            if ( !preg_match ($allowedInputType[$vartype], $query) ) {
                $error = true;
            } else  {
                $error = false;
            }
            
        }
        
        if (!$error) {
        
	        switch ($vartype) {
	            case 'BasicPattern':
	                $sparqlQuery =  'SELECT DISTINCT ?entity ?label FROM <' . $this->_privateConfig->storeModel . '> { 
			        	?entity a ?t . ?entity <' . EF_RDFS_LABEL . '> ?label .
			        	FILTER (
			        		REGEX(?label,"' . addcslashes($query,'"') . '", "i") &&
			        		REGEX( STR(?t), "' .  addcslashes($vartype,'"') . '", "i")
			        	)
			    		} LIMIT ' . $limit;
	        	        try {
				            $res = $this->_erfurt->getStore()->sparqlQuery($sparqlQuery);
				        } catch (Exception $e) {
				            $error = true;
				            $res = array();
				        }
				        foreach ($res as $row) {
				            $ret[] = json_encode( array($row['entity'],$row['label']));
				        }
	                break;
	            case 'LITERAL':
			        $sparqlQuery .=  $sparqlQuery . '{ 
			        	?s ?p ?entity . 
			        	FILTER (
			        		isLiteral(?entity) &&
			        		REGEX(?entity, "' . addcslashes($query,'"') . '","i")
			        	)
			    		} LIMIT ' . $limit;
        	        try {
			            $res = $this->_erfurt->getStore()->sparqlQuery($sparqlQuery);
			        } catch (Exception $e) {
			            $error = true;
			            $res = array();
			        }
			        foreach ($res as $value) {
			            $ret[] = $value['entity'];
			        }
			        break;
	            case 'CLASS' :
	                break;
	            case 'PROPERTY':
	                break;
	            case 'RESOURCE' :
                    $sparqlQuery =  'SELECT DISTINCT ?entity FROM <' .  (string) $model . '> WHERE { 
			        	?entity ?p ?o . 
			        	?entity <' . EF_RDFS_LABEL . '> ?label
			        	FILTER (
			        		REGEX( ?label, "' . addcslashes($query,'"') . '","i") ||
			        		REGEX( STR(?entity), "' . addcslashes($query,'"') . '","i")
			        	)
			    		} LIMIT ' . $limit;
        	        try {
			            $res = $this->_erfurt->getStore()->sparqlQuery($sparqlQuery);
			        } catch (Exception $e) {
			            $error = true;
			            $res = array();
			        }
			        foreach ($res as $value) {
			            $ret[] = $value['entity'];
			        }
	                break;
	                
	            default:
	                break;
	        }
            
        }
        
        echo implode(PHP_EOL, $ret);
    }
    
    /**
     * load a basic pattern as json
     */
    public function loadpatternAction() {
        
        $this->_helper->viewRenderer->setNoRender();
        $this->_helper->layout->disableLayout();
        
        $uri = $this->_request->getParam('uri','http:/null/uri');
        
        $schema = array (
	    	'PatternVariable'        => 'http://ns.ontowiki.net/SysOnt/EvolutionPattern/PatternVariable' ,
	        'SelectQuery'            => 'http://ns.ontowiki.net/SysOnt/EvolutionPattern/SelectQuery' ,
	        'UpdateQuery_Insert'     => 'http://ns.ontowiki.net/SysOnt/EvolutionPattern/UpdateQuery_Insert' ,
	        'UpdateQuery_Delete'     => 'http://ns.ontowiki.net/SysOnt/EvolutionPattern/UpdateQuery_Delete' ,
	        'SubPattern'		     => 'http://ns.ontowiki.net/SysOnt/EvolutionPattern/SubPattern' ,
	        'ComplexPattern'         => 'http://ns.ontowiki.net/SysOnt/EvolutionPattern/ComplexPattern' ,
	        'BasicPattern'           => 'http://ns.ontowiki.net/SysOnt/EvolutionPattern/BasicPattern' ,
	        'hasPatternVariable'     => 'http://ns.ontowiki.net/SysOnt/EvolutionPattern/hasPatternVariable' ,
	        'hasUpdateQuery'         => 'http://ns.ontowiki.net/SysOnt/EvolutionPattern/hasUpdateQuery' ,
	        'hasBasicPattern'        => 'http://ns.ontowiki.net/SysOnt/EvolutionPattern/hasBasicPattern' ,
	        'hasSubPattern'          => 'http://ns.ontowiki.net/SysOnt/EvolutionPattern/hasSubPattern' ,
	        'hasSelectQuery'	     => 'http://ns.ontowiki.net/SysOnt/EvolutionPattern/hasSelectQuery' ,
	        'updatePatternObject'    => 'http://ns.ontowiki.net/SysOnt/EvolutionPattern/updatePatternObject' ,
	        'updatePatternPredicate' => 'http://ns.ontowiki.net/SysOnt/EvolutionPattern/updatePatternPredicate' ,
	        'updatePatternSubject'   => 'http://ns.ontowiki.net/SysOnt/EvolutionPattern/updatePatternSubject' ,
        );
        
		$sparqlQuery =  'SELECT DISTINCT ?s ?p ?o FROM <' . $this->_privateConfig->storeModel . '> { 
			{ 
			<' . $uri . '> a <' . $schema['BasicPattern'] . '> .
			?s ?p ?o . FILTER (sameTerm(?s,<' . $uri . '>)) .
    		} UNION {
    		<' . $uri . '> a <' . $schema['BasicPattern'] . '> .
    		<' . $uri . '> <' . $schema['hasPatternVariable'] . '> ?s .
    		?s ?p ?o .
    		} UNION {
			<' . $uri . '> a <' . $schema['BasicPattern'] . '> .
    		<' . $uri . '> <' . $schema['hasSelectQuery'] . '> ?s .
    		?s ?p ?o .
    		} UNION {
			<' . $uri . '> a <' . $schema['BasicPattern'] . '> .
    		<' . $uri . '> <' . $schema['hasUpdateQuery'] . '> ?s .
    		?s ?p ?o .
    		}
			}';
		
        $result = $this->_erfurt->getStore()->sparqlQuery($sparqlQuery, array(STORE_USE_AC => false));
        
        $types = array();
        $stmt = array();
        $variable = array();
        $select = array();
        $update  = array('insert' => array(), 'delete' => array());
        
        foreach ($result as $row) {
            if ($row['p'] === EF_RDF_TYPE) {
                $types[$row['s']] = $row['o'];
            } else {
	            $stmt[$row['s']][$row['p']][] = $row['o'];
            }
        }
        
        $basicPattern = new BasicPattern();
        
        foreach ($stmt as $s => $data) {
            if ($types[$s] === $schema['BasicPattern']) {
                if (array_key_exists(EF_RDFS_LABEL,$data)) {
                    $basicPattern->setLabel($data[EF_RDFS_LABEL][0]);
                } else {
                   $basicPattern->setLabel('');
                }
                if (array_key_exists(EF_RDFS_COMMENT,$data)) {
                    $basicPattern->setDescription($data[EF_RDFS_COMMENT][0]);
                } else {
                   $basicPattern->setDescription('');
                }
            }
            if (strpos($types[$s],$schema['PatternVariable']) === 0  ) {
                $type = substr($types[$s],strlen($schema['PatternVariable']) + 1 );
                if (array_key_exists(EF_RDFS_LABEL,$data) && array_key_exists(EF_RDFS_LABEL, $data) && $type) {
                    $basicPattern->addVariable($data[EF_RDFS_LABEL][0], $type, $data[EF_RDFS_COMMENT][0]);
                }
            }
            
            if ( $types[$s] === $schema['SelectQuery'] ) {
                if ( array_key_exists(EF_RDFS_LABEL, $data)) {
                    $basicPattern->setSelectQuery($data[EF_RDFS_LABEL][0]);
                }
            }
            
            if ( $types[$s] === $schema['UpdateQuery_Insert'] ) {
                if (
                    array_key_exists($schema['updatePatternObject'],$data) &&
                    array_key_exists($schema['updatePatternPredicate'], $data) &&
                    array_key_exists($schema['updatePatternSubject'], $data)
                ) {
                    $pattern = $data[$schema['updatePatternSubject']][0] . ' ' .
                        $data[$schema['updatePatternPredicate']][0] . ' ' .
                        $data[$schema['updatePatternObject']][0]; 
                    $basicPattern->addUpdateQuery($pattern, 'insert');
                }
            }
            
                    
            if ( $types[$s] === $schema['UpdateQuery_Delete'] ) {
                if (
                    array_key_exists($schema['updatePatternObject'],$data) &&
                    array_key_exists($schema['updatePatternPredicate'], $data) &&
                    array_key_exists($schema['updatePatternSubject'], $data)
                ) {
                    $pattern = $data[$schema['updatePatternSubject']][0] . ' ' .
                        $data[$schema['updatePatternPredicate']][0] . ' ' .
                        $data[$schema['updatePatternObject']][0]; 
                    $basicPattern->addUpdateQuery($pattern, 'delete');
                }
            }
        }
        
        echo $basicPattern->toArray(true);

    }

}
