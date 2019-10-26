<?php

namespace Icinga\Module\Nomad\ProvidedHook\Director;

use Icinga\Module\Director\Hook\ImportSourceHook;
use Icinga\Module\Director\Web\Form\QuickForm;
use SensioLabs\Consul\ServiceFactory;

/**
 * Class ImportSource
 *
 * This is where we provide an Import Source for the Icinga Director
 */
class ImportSource extends ImportSourceHook
{
    public function getName()
    {
        return 'HashiCorp Nomad';
    }

    public function fetchData()
    {
        $sf = new ServiceFactory(array('base_uri' => $this->getSetting('consul_url')));
        $agent = $sf->get('catalog');

        $nomadClients = json_decode($agent->service($this->getSetting('nomad_client_service'))->getBody());
        $nomadServices = [];
        foreach ($nomadClients as $client) {
            $node = json_decode($agent->node($client->Node)->getBody(), true);
            $nomadServices = array_merge(
                $nomadServices,
                array_filter(
                    $node['Services'],
                    "getNomadTasks",
                    ARRAY_FILTER_USE_KEY
                )
            );
        }
        return $nomadServices;
    }

    public function listColumns()
    {
        return array_keys((array) current($this->fetchData()));
    }

    /**
     * @inheritdoc
     */
    public static function getDefaultKeyColumnName()
    {
        return 'Node';
    }

    /**
     * @inheritdoc
     * @throws \Zend_Form_Exception
     */
    public static function addSettingsFormFields(QuickForm $form)
    {
        /** @var $form \Icinga\Module\Director\Forms\ImportSourceForm */
        $form->addElement('text', 'consul_url', array(
            'label'        => 'HTTP API URL',
            'required'     => true,
            'value'        => 'http://127.0.0.1:8500',
        ));
        $form->addElement('text', 'nomad_client_service', array(
            'label'        => 'Consul Nomad Client Service',
            'required'     => true,
            'value'        => 'nomad-client',
        ));
        return;
    }

    /**
     * @param $key
     * @return false|int
     */
    protected function getNomadTasks($key)
    {
        return preg_match("#^_nomad-task(.*)$#i", $key);
    }
}
