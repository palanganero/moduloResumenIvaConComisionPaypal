<?php

/**
 * 2007-2021 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 *  @author    PrestaShop SA <contact@prestashop.com>
 *  @copyright 2007-2021 PrestaShop SA
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class moduloResumenIvaConComisionPaypal extends Module {

    protected $config_form = false;

    public function __construct() {
        $this->name = 'moduloResumenIvaConComisionPaypal';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'jose luis guillan suarez';
        $this->need_instance = 0;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('moduloResumenIvaConComisionPaypal');
        $this->description = $this->l('Este modulo calcula el resumen del iva en un intervalo de tiempo, clasificado por tipos, y lo puede exportar a excel');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install() {
        Configuration::updateValue('MIMODULOMISMADB_LIVE_MODE', false);

        return parent::install() &&
                $this->registerHook('header') &&
                $this->registerHook('actionPaymentConfirmation') &&
                $this->registerHook('backOfficeHeader');
    }

    public function uninstall() {
        Configuration::deleteByName('MIMODULOMISMADB_LIVE_MODE');

        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent() {
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool) Tools::isSubmit('calcularIva')) == true) {
            $this->postProcess();
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules') . '&configure=' . $this->name . '&conf=6');
        }
        if (((bool) Tools::isSubmit('exportarExcel')) == true) {
            $this->postProcess();
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules') . '&configure=' . $this->name . '&conf=6');
        }
        $this->context->smarty->assign('module_dir', $this->_path);

        $output = $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configure.tpl');

        return $output . $this->renderForm();
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm() {

        $values = array();
        $this->fields_form = array();
        $this->context->controller->addJqueryUI('ui.datepicker');
        $defaultDate = date('Y-m-d');
        $values['my_date_desde'] = Tools::getValue('my_date_desde', Configuration::get($this->name . '_my_date_desde'));
        $values['my_date_hasta'] = Tools::getValue('my_date_hasta', Configuration::get($this->name . '_my_date_hasta'));
        $values['comisionFija'] = Tools::getValue('comisionFija', Configuration::get($this->name . '_comisionFija'));
        $values['comisionVariable'] = Tools::getValue('comisionVariable', Configuration::get($this->name . '_comisionVariable'));
        /*
          if (!Configuration::get($this->name . 'my_date_desde')) {
          //$values['my_date_desde'] = Tools::getValue('my_date_desde', $defaultDate);
          $values['my_date_desde'] = Tools::getValue('my_date_desde', Configuration::get($this->name . '_my_date_desde'));
          } else {
          $values['my_date_desde'] = Tools::getValue('my_date_desde', Configuration::get($this->name . '_my_date_desde'));
          }
          if (!Configuration::get($this->name . 'my_date_hasta')) {
          //$values['my_date_hasta'] = Tools::getValue('my_date_hasta', $defaultDate);
          $values['my_date_hasta'] = Tools::getValue('my_date_hasta', Configuration::get($this->name . '_my_date_hasta'));
          } else {
          $values['my_date_hasta'] = Tools::getValue('my_date_hasta', Configuration::get($this->name . '_my_date_hasta'));
          } */
        //$values['iva'] = Tools::getValue('iva', Configuration::get($this->name . '_iva'));

        $db = \Db::getInstance();
        $sqlTipos = "select tax_name,tax_rate from order_detail group by tax_rate";
        $tipos = $db->executeS($sqlTipos);

        foreach ($tipos as $t) {
            $values[str_replace(" ", "", str_replace("%", '', $t['tax_name']))] = Tools::getValue(str_replace(" ", "", str_replace("%", '', $t['tax_name'])), Configuration::get($this->name . '_' . str_replace(" ", "", str_replace("%", '', $t['tax_name']))));
        }
        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'Submit' . $this->name;
        //$helper->submit_action = 'submitButton';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $values,
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        //$style = '<style>.form-group.col-lg-9:nth-child(1) {width: 48%;display: inline-block;margin-right: 2%;} .form-group.col-lg-9:nth-child(2) {width: 48%;display: inline-block;}</style>';
        //return $style . $helper->generateForm(array($this->getConfigForm()));
        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm() {

        $campos = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Settings'),
                    'icon' => 'icon-cogs'
                ),
               
                    
                'input' => array(
                    array(
                        'type' => 'date',
                        'label' => $this->l('Desde'),
                        'name' => 'my_date_desde',
                        'required' => true,
                        //'class' => 'col-lg-6',
                        //'class' => 'my-custom-class',
                    ),
                    array(
                        'type' => 'date',
                        'label' => $this->l('Hasta'),
                        'name' => 'my_date_hasta',
                        'required' => true,
                        //'class' => 'col-lg-6',
                        //'class' => 'my-custom-class',
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Comision fija'),
                        'name' => 'comisionFija',
                        'required' => true,
                        'class' => 'col-lg-6',
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Comision variable'),
                        'name' => 'comisionVariable',
                        'required' => true,
                        'class' => 'col-lg-6',
                    ),
                ),
                /*
                  'submit' => array(
                  'title' => $this->l('Save settings'),
                  'class' => 'button btn btn-default pull-right',
                  'name' => 'submitSave',
                  ), */
                'buttons' => [
                    [
                        'title' => $this->l('Calcular Iva'),
                        'name' => 'calcularIva',
                        'type' => 'submit',
                        'class' => 'btn btn-default pull-right',
                    ],
                    [
                        'title' => $this->l('Exportar a Excel'),
                        'name' => 'exportarExcel',
                        'type' => 'submit',
                        'class' => 'btn btn-default pull-right',
                    ],
                ],
                
            ),
        );

        $db = \Db::getInstance();
        $sqlTipos = "select tax_name,tax_rate from order_detail group by tax_rate";
        $tipos = $db->executeS($sqlTipos);
        $i = 0;
        foreach ($tipos as $t) {

            $miCampo = array(
                'type' => 'text',
                'label' => $this->l($t['tax_name']),
                'name' => str_replace(" ", "", str_replace("%", '', $t['tax_name'])),
                    //'name' => '21.000',
            );
            array_push($campos['form']['input'], $miCampo);
            $i++;
        }


        //$helper = new HelperForm(); 
        // Estilo para mostrar ambos campos en la misma línea
        //$style = '<style>label[for="comisionFija"], label[for="comisionVariable"] { display: inline-block; width: 100px; } #form-configuration .form-group { display: flex; align-items: center; }</style>';
        //$output = $style . $helper->generateForm($campos);

        return $campos;
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues() {
        $campos = array(
            'CAMPOID' => Configuration::get('CAMPOID', null),
            'MIMODULOMISMADB_ACCOUNT_USUARIO' => Configuration::get('MIMODULOMISMADB_ACCOUNT_USUARIO', null),
            'MIMODULOMISMADB_ACCOUNT_PASSWORD' => Configuration::get('MIMODULOMISMADB_ACCOUNT_PASSWORD', null),
            'my_date_desde' => Configuration::get('my_date_desde', null),
            'my_date_hasta' => Configuration::get('my_date_hasta', null),
                //'iva' => Configuration::get('iva', null),
        );

        return $campos;
    }

    /**
     * Save form data.
     */
    protected function postProcess() {



        $submit_action = Tools::isSubmit('calcularIva') ? 'calcularIva' : 'exportarExcel';

        if ($submit_action === 'calcularIva') {
            $this->calcularIva();
        }
        if ($submit_action === 'exportarExcel') {
            $this->exportarExcel();
            exit;
        }
        /*
          if (Tools::isSubmit('Submit' . $this->name)) {


          } */
    }

    protected function calcularIva() {
        if (Tools::getValue('my_date_desde')) {

            Configuration::updateValue($this->name . '_my_date_desde', Tools::getValue('my_date_desde'));
        }
        if (Tools::getValue('my_date_hasta')) {

            Configuration::updateValue($this->name . '_my_date_hasta', Tools::getValue('my_date_hasta'));
        }

        if (Tools::getValue('comisionFija')) {

            Configuration::updateValue($this->name . '_comisionFija', Tools::getValue('comisionFija'));
        }
        if (Tools::getValue('comisionVariable')) {

            Configuration::updateValue($this->name . '_comisionVariable', Tools::getValue('comisionVariable'));
        }

        $fechaDesde = Configuration::get($this->name . '_my_date_desde', null) . " 00:00:00";
        $fechaHasta = Configuration::get($this->name . '_my_date_hasta', null) . " 23:59:59";

        $comisionFija = Configuration::get($this->name . '_comisionFija', null);
        $comisionVariable = Configuration::get($this->name . '_comisionVariable', null);

        //mail("luilli.guillan@gmail.com", "fecha mes anterior", $fechaMesAnteriorDesde." ".$fechaMesAnteriorHasta);

        $db = \Db::getInstance();
        $sqlTipos = "select tax_name,tax_rate from order_detail group by tax_rate";
        $tipos = $db->executeS($sqlTipos);
        foreach ($tipos as $t) {
            $ivaAcumulado = 0;
            $sql = "select * from orders where (current_state = 2 or current_state=4) and date_add BETWEEN '" . $fechaDesde . "' AND '" . $fechaHasta . "'";
            $result = $db->executeS($sql);

            foreach ($result as $row) {
                $this->calcularVentasMesPasado($row["date_add"]);
                //mail("luilli.guillan@gmail.com", "fecha", $row["date_add"]);
                $metodoDePago = $row["payment"];
                $consultaIva = "select * from order_detail where (id_order='" . $row['id_order'] . "' and tax_rate='" . $t['tax_rate'] . "')";
                $resultConsultaIva = $db->executeS($consultaIva);
                foreach ($resultConsultaIva as $rowConsultaIva) {

                    if ($metodoDePago == "Redsys - Tarjeta") {
                        //mail("luilli.guillan@gmail.com", "metodo de pago redsys", $metodoDePago);

                        $totalConIva = $rowConsultaIva["total_price_tax_incl"];
                        if ($totalConIva == 0) {
                            $comisionFija = 0;
                        }
                        //else{$comisionFija=0.35;}
                        $totalConIvaMenosComisionPaypal = $totalConIva - ($totalConIva * $comisionVariable / 100 + $comisionFija);
                        $totalSinIvaMenosComisionPaypal = $totalConIvaMenosComisionPaypal / ($rowConsultaIva["tax_rate"] / 100 + 1);
                        if ($totalConIvaReembolsado == 0) {
                            $comisionFijaReembolso = 0;
                        } else {
                            $comisionFijaReembolso = comisionFija;
                        }
                        $totalConIvaReembolsado = $rowConsultaIva["total_refunded_tax_incl"];
                        $totalConIvaMenosComisionPaypalReembolsado = $totalConIvaReembolsado - ($totalConIvaReembolsado * $comisionVariable / 100 + $comisionFijaReembolso);
                        $totalSinIvaMenosComisionPaypalReembolsado = $totalConIvaMenosComisionPaypalReembolsado / ($rowConsultaIva["tax_rate"] / 100 + 1);

                        //$ivaAcumulado+=$totalSinIvaMenosComisionPaypal-$totalSinIvaMenosComisionPaypalReembolsado;
                        $ivaAcumulado += ($totalSinIvaMenosComisionPaypal) * $rowConsultaIva["tax_rate"] / 100;
                        //mail("luilli.guillan@gmail.com","reembolsado", $totalSinIvaMenosComisionPaypalReembolsado);
                    } else {
                        $ivaAcumulado += ($rowConsultaIva["total_price_tax_excl"] - $rowConsultaIva["total_refunded_tax_excl"]) * $rowConsultaIva["tax_rate"] / 100;
                        //mail("luilli.guillan@gmail.com","metodo de pago otro", $metodoDePago);
                    }
                }
            }
            //mail("luilli.guillan@gmail.com", $t['tax_rate'], $ivaAcumulado);
            Configuration::updateValue($this->name . '_' . str_replace(" ", "", str_replace("%", '', $t['tax_name'])), $ivaAcumulado);
            //Configuration::updateValue($this->name . '_my_date_desde', $fechaDesde);
            //Configuration::updateValue($this->name . '_my_date_hasta', $fechaHasta);
        }
    }

    protected function calcularVentasMesPasado($fecha) {
        //$monto=0;
        $fechaActual = date('Y-m', strtotime($fecha)) . "-01 00:00:00";
        $fechaMesAnteriorDesde = date("Y-m-d", strtotime($fechaActual . "- 1 month")) . " 00:00:00";
        $fechaMesAnteriorHasta = $fechaActual;
        mail("luilli.guillan@gmail.com", "rango de fechas", $fechaMesAnteriorDesde . "-" . $fechaMesAnteriorHasta);
        $db = \Db::getInstance();
        $sqlVentasMesPasado = $sql = "select sum(total_price_tax_incl) from order_detail where id_order in (select id_order from orders where (current_state = 2 or current_state=4) and date_add BETWEEN '" . $fechaMesAnteriorDesde . "' AND '" . $fechaMesAnteriorHasta . "')";
        //$ventasMesPasado = $db->executeS($sqlVentasMesPasado);
        $ventasMesPasado = $db->getValue($sqlVentasMesPasado);
        /*
          foreach ($ventasMesPasado as $vmp) {

          $sqlVentasMesPasadoDetail = "select sum(total_price_tax_incl) as monto from order_detail where (id_order='" . $vmp['id_order'] ."')";
          $ventasMesPasadoDetail = $db->executeS($sqlVentasMesPasadoDetail);
          foreach($ventasMesPasadoDetail as $vmpd)
          {
          $monto+=$vmpd["monto"];
          //mail("luilli.guillan@gmail.com", "montos parciales", $vmpd["monto"]);
          }
          } */
        //$monto = array_column($ventasMesPasado, 'monto');
        //foreach ($ventasMesPasado as $vmp)

        mail("luilli.guillan@gmail.com", "monto", $ventasMesPasado);
    }

    protected function exportarExcel() {
        if (Tools::getValue('my_date_desde')) {

            Configuration::updateValue($this->name . '_my_date_desde', Tools::getValue('my_date_desde'));
        }
        if (Tools::getValue('my_date_hasta')) {

            Configuration::updateValue($this->name . '_my_date_hasta', Tools::getValue('my_date_hasta'));
        }
        $fechaDesde = Configuration::get($this->name . '_my_date_desde', null) . " 00:00:00";
        $fechaHasta = Configuration::get($this->name . '_my_date_hasta', null) . " 23:59:59";

        $directorioActual = _PS_UPLOAD_DIR_;
        $filenameCreado = $directorioActual . 'example.csv';
        $file = fopen($filenameCreado, 'w');
        fwrite($file, "Referencia Pedido;Nombre Producto;Referencia Producto;Tipo Iva;Iva;'Total sin iva';Total" . "\n");

        $db = \Db::getInstance();
        $sql = "select * from orders where date_add BETWEEN '" . $fechaDesde . "' AND '" . $fechaHasta . "' order by date_add desc";
        $result = $db->executeS($sql);

        foreach ($result as $row) {
            $referenciaPedido = $row['reference'];
            $consultaIva = "select * from order_detail where (id_order='" . $row['id_order'] . "')";
            $linea = $db->executeS($consultaIva);
            foreach ($linea as $l) {
                $iva = ($l['total_price_tax_incl'] - $l['total_price_tax_excl']) - ($l['total_refunded_tax_incl'] - $l['total_refunded_tax_excl']);
                $priceTaxExcluded = $l['total_price_tax_excl'] - $l['total_refunded_tax_excl'];
                $priceTaxIncluded = $l['total_price_tax_incl'] - $l['total_refunded_tax_incl'];
                fwrite($file, $referenciaPedido . ";" . $l['product_name'] . ";" . $l['product_reference'] . ";" . number_format((double) $l['tax_rate'], 2, ',', '') . ";" . number_format((double) $iva, 2, ',', '') . ";" . number_format((double) $priceTaxExcluded, 2, ',', '') . ";" . number_format((double) $priceTaxIncluded, 2, ',', '') . "\n");
            }
        }

        fclose($file);

        $filenameDescargado = $directorioActual . "example.csv";

        $download_filename = 'ventasTiendaOnline.csv';
        $download_path = _PS_DOWNLOAD_DIR_ . $download_filename;
        $file_content = file_get_contents($filenameDescargado);
        file_put_contents($download_path, $file_content);

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=' . basename($download_path));
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($download_path));
        readfile($download_path);
    }

    /**
     * Add the CSS & JavaScript files you want to be loaded in the BO.
     */
    public function hookBackOfficeHeader() {
        if (Tools::getValue('module_name') == $this->name) {
            $this->context->controller->addJS($this->_path . 'views/js/back.js');
            $this->context->controller->addCSS($this->_path . 'views/css/back.css');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader() {

        $this->context->controller->addJS($this->_path . '/views/js/front.js');
        $this->context->controller->addCSS($this->_path . '/views/css/front.css');
    }

}
