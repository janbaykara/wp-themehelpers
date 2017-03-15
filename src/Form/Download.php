<?php
namespace Svbk\WP\Helpers\Form;

use Svbk\WP\Helpers\Mandrill;
use Mandrill_Error;

class Download extends Subscribe {

    public $field_prefix = 'dl';
    public $md_apikey = '';
    public $templateName = '';
    
    public $messageDefaults = array(
        'html' => 'default HTML content',
        //'text' => 'default TEXT content',        
        'track_opens' => null,
        'track_clicks' => null,
        'auto_text' => null,
        'auto_html' => true,
        'inline_css' => null,
        'url_strip_qs' => null,
        'preserve_recipients' => null,
        'view_content_link' => null,
        'tracking_domain' => null,
        'signing_domain' => null,
        'return_path_domain' => null,
        'merge' => true,
        'merge_language' => 'mailchimp',
        'tags' => array('download-request'),
        'subaccount' => '',
    );  
    
    public function processInput($input_filters=array()){
        
        $input_filters['fid'] = FILTER_VALIDATE_INT;
        
        return parent::processInput($input_filters);
    }

    public function processSubmission(){
        
        parent::processSubmission();
        
        if(empty($this->errors) && $this->checkPolicy('policy_newsletter')){
            parent::mainAction();
        }
    
    }
    
    protected function mainAction(){
        
        if( !empty( $this->md_apikey ) ){
            $mc = new Mandrill( $this->md_apikey );
            
            try {
                $mandrill = new Mandrill( $this->md_apikey );

                if($this->templateName){
                    $results = $mandrill->messages->sendTemplate($this->templateName, array(), $this->messageParams());
                } else {
                    $results = $mandrill->messages->send($this->messageParams());
                }
                
                   if( !is_array($results) || !isset($results[0]['status']) ){
                    throw new Mandrill_Error( __('The requesto to our mail server failed, please try again later or contact the site owner.', 'svbk-helpers') );
                } 
                
                $errors = $mandrill->getResponseErrors($results);
                
                foreach($errors as $error){
                    $this->addError($error, 'email');
                }
                

            } catch(Mandrill_Error $e) {
                $this->addError( $e->getMessage() );
            }            
            
        }
        
    }    
    
    protected function getRecipients(){
        return array(
            array(
                'email' => $this->getInput('email'),
                'name' => ucfirst($this->getInput('fname')) . ' ' .  ucfirst($this->getInput('lname')),
                'type' => 'to'
            )
        );
    }
    
    protected function getGlobalMergeTags(){
        
        $mergeTags = Mandrill::castMergeTags($this->inputData, 'INPUT_');
        
        $mergeTags[] = array(
            'name' => 'DOWNLOAD_URL',
            'content' => esc_url( $this->getDownloadLink() )
        );
        
        var_dump($mergeTags);
        
        return $mergeTags;
    }
    
    protected function getDownloadLink(){
        return wp_get_attachment_url( $this->getInput('fid') );
    }
    
    protected function messageParams(){
        
        return array_merge_recursive(
            $this->messageDefaults,
            array(
                'to' => $this->getRecipients(),
                'global_merge_vars' => $this->getGlobalMergeTags(),
                'metadata' => array(
                    'website' => home_url( '/' )
                )
            )
        );        
    }
    
    public function renderParts($action, $attr=array()){
        $output = parent::renderParts($action, $attr);
        
        $output['input']['file'] = '<input type="hidden" name="' . $this->fieldName('fid') . '" value="' . $attr['file'] . '" >';
        
        return $output;
    }
    
    
    protected function validateInput(){
        
        parent::validateInput();
        
        $post = get_post( (int)$this->getInput('fid') );
        
        if( !$post || ('attachment' != $post->post_type) ) {
            $this->addError( __('The specified download doesn\'t exists anymore. Please contact site owner', 'svbk-helpers' ) );
        }
        
    }    
    
}