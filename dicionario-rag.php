<?php
/*
Plugin Name: Dicionario RAG - Acceso ao Dicionario da Real Academia Galega
Plugin URI: https://github.com/sanchezanxo/dicionario-rag
Description: Plugin de WordPress que permite acceder ao dicionario oficial da Real Academia Galega vía interface gráfica, ante a ausencia de API pública. Inclúe definicións, conxugacións verbais e funcionalidade completa.
Version: 1.0.0
Author: Anxo Sanchez Garcia
Author URI: https://www.anxosanchez.com
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: dicionario-rag
Domain Path: /languages
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4

Este programa é software libre; podes redistribuílo e/ou modificalo  
nos termos da Licenza Pública Xeral GNU, tal e como foi publicada pola  
Free Software Foundation; ben na versión 2 da licenza, ou  
(se o prefires) en calquera versión posterior.

Este programa distribúese coa esperanza de que sexa útil,  
pero SEN NINGUNHA GARANTÍA; nin sequera coa garantía implícita de  
COMERCIALIZACIÓN nin de IDONEIDADE PARA UN PROPÓSITO PARTICULAR.  
Consulta a Licenza Pública Xeral GNU para máis detalles.

*/

// Prevenir acceso directo ao ficheiro
if (!defined('ABSPATH')) {
    exit('Acceso directo non permitido.');
}

// Definir constantes do plugin para evitar hardcoding de valores
define('ASG_DICIONARIO_RAG_VERSION', '1.0.0');
define('ASG_DICIONARIO_RAG_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ASG_DICIONARIO_RAG_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('ASG_DICIONARIO_RAG_TEXT_DOMAIN', 'dicionario-rag');

// URLs e configuración da RAG
define('ASG_DICIONARIO_RAG_BASE_URL', 'https://academia.gal/dicionario');
define('ASG_DICIONARIO_RAG_TIMEOUT', 30);
define('ASG_DICIONARIO_RAG_USER_AGENT', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');

/**
 * Clase principal do plugin Dicionario RAG
 * 
 * Esta clase xestiona a funcionalidade principal do plugin, incluíndo:
 * - Carga de assets (CSS e JavaScript)
 * - Rexistro do shortcode [dicionario_rag]
 * - Manexo de peticións AJAX para consultas ao dicionario
 * - Configuración de hooks e filtros de WordPress
 */
class ASG_DicionarioRAG {
    
    /**
     * Constructor da clase principal
     * 
     * Rexistra todos os hooks necesarios para o funcionamento do plugin:
     * - Carga de assets no frontend
     * - Creación do shortcode
     * - Manexo de peticións AJAX (para usuarios logueados e anónimos)
     * - Hook de desinstalación
     */
    public function __construct() {
        // Hooks de inicialización
        add_action('wp_enqueue_scripts', array($this, 'asg_enqueue_assets'));
        add_shortcode('dicionario_rag', array($this, 'asg_mostrar_formulario'));
        add_action('wp_ajax_asg_consultar_rag', array($this, 'asg_manejar_consulta_ajax'));
        add_action('wp_ajax_nopriv_asg_consultar_rag', array($this, 'asg_manejar_consulta_ajax'));
        
        // Hook de desinstalación
        register_uninstall_hook(__FILE__, array('ASG_DicionarioRAG', 'asg_uninstall'));
    }
    
    /**
     * Cargar e rexistrar os assets (CSS e JavaScript) do plugin
     * 
     * Esta función carga os ficheiros CSS e JS necesarios para o funcionamento
     * do plugin no frontend, inclúe:
     * - Estilos CSS para a interface
     * - Script JavaScript para funcionalidade AJAX
     * - Variables localizadas (URL AJAX e nonce de seguridade)
     */
    public function asg_enqueue_assets() {
        // Cargar jQuery (dependencia)
        wp_enqueue_script('jquery');
        
        // CSS do plugin
        wp_enqueue_style(
            'asg-dicionario-rag-css',
            ASG_DICIONARIO_RAG_PLUGIN_URL . 'assets/css/dicionario-rag.css',
            array(),
            ASG_DICIONARIO_RAG_VERSION
        );
        
        // JavaScript do plugin
        wp_enqueue_script(
            'asg-dicionario-rag-js',
            ASG_DICIONARIO_RAG_PLUGIN_URL . 'assets/js/dicionario-rag.js',
            array('jquery'),
            ASG_DICIONARIO_RAG_VERSION,
            true
        );
        
        // Localizar script con variables necesarias para AJAX
        wp_localize_script('asg-dicionario-rag-js', 'dicionario_vars', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('asg_dicionario_rag_nonce')
        ));
    }
    
    /**
     * Render do shortcode [dicionario_rag]
     * 
     * Esta función xenera o HTML do formulario de busca que se mostra
     * no frontend cando se usa o shortcode. Inclúe:
     * - Formulario con input de texto
     * - Botóns para definición e conxugación
     * - Elementos para mostrar loading e resultados
     * - HTML semántico e accesible
     * 
     * @return string HTML do formulario de busca
     */
    public function asg_mostrar_formulario() {
        ob_start();
        ?>
        <div class="dicionario-rag-container">            
            <form id="dicionario-form" role="search">
                <div class="form-group">
                    <label for="palabra-input">
                        <?php echo esc_html__('Introduce unha palabra en galego:', ASG_DICIONARIO_RAG_TEXT_DOMAIN); ?>
                    </label>
                    <input 
                        type="text" 
                        id="palabra-input" 
                        placeholder="<?php echo esc_attr__('Exemplo: comer', ASG_DICIONARIO_RAG_TEXT_DOMAIN); ?>" 
                        required 
                        maxlength="100"
                        autocomplete="off"
                    >
                    <button type="submit" id="consultar-btn">
                        <?php echo esc_html__('Definición', ASG_DICIONARIO_RAG_TEXT_DOMAIN); ?>
                    </button>
                    <button type="button" id="conxugar-btn">
                        <?php echo esc_html__('Conxugación', ASG_DICIONARIO_RAG_TEXT_DOMAIN); ?>
                    </button>
                </div>
            </form>
            
            <div id="loading" style="display: none;" role="status" aria-live="polite">
                <p><?php echo esc_html__('🔄 Consultando o dicionario da RAG...', ASG_DICIONARIO_RAG_TEXT_DOMAIN); ?></p>
            </div>
            
            <div id="resultado" role="region" aria-live="polite"></div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Manejar peticións AJAX para consultas ao dicionario
     * 
     * Esta función procesa as peticións AJAX que veñen do frontend para:
     * - Verificar a seguridade mediante nonce
     * - Sanitizar e validar os datos de entrada
     * - Delegas a consulta á clase ASG_DicionarioRAGConsulta 
     * - Devolver resposta JSON ao frontend
     * - Manexar erros e excepcións de forma segura
     */
    public function asg_manejar_consulta_ajax() {
        // Verificar nonce de seguridade
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'asg_dicionario_rag_nonce')) {
            wp_send_json_error(esc_html__('Erro de seguridade', ASG_DICIONARIO_RAG_TEXT_DOMAIN));
            return;
        }
        
        // Sanitizar e validar inputs
        $palabra = sanitize_text_field($_POST['palabra'] ?? '');
        $tipo = sanitize_text_field($_POST['tipo'] ?? 'definicion');
        
        // Validacións de entrada
        if (empty($palabra)) {
            wp_send_json_error(esc_html__('Non se proporcionou ningunha palabra', ASG_DICIONARIO_RAG_TEXT_DOMAIN));
            return;
        }
        
        if (strlen($palabra) > 100) {
            wp_send_json_error(esc_html__('A palabra é demasiado longa', ASG_DICIONARIO_RAG_TEXT_DOMAIN));
            return;
        }
        
        if (!in_array($tipo, ['definicion', 'conxugacion'])) {
            wp_send_json_error(esc_html__('Tipo de consulta non válido', ASG_DICIONARIO_RAG_TEXT_DOMAIN));
            return;
        }
        
        // Crear instancia da clase de consulta
        $dicionario = new ASG_DicionarioRAGConsulta ();
        
        try {
            if ($tipo === 'conxugacion') {
                $resultado = $dicionario->asg_buscarConxugacion($palabra);
            } else {
                $resultado = $dicionario->asg_buscarPalabra($palabra);
            }
            
            if ($resultado) {
                wp_send_json_success($resultado);
            } else {
                wp_send_json_error(esc_html__('Non se atopou información para esta palabra', ASG_DICIONARIO_RAG_TEXT_DOMAIN));
            }
        } catch (Exception $e) {
            // Log do erro (só en modo debug)
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('ASG Dicionario RAG Error: ' . $e->getMessage());
            }
            wp_send_json_error(esc_html__('Erro interno. Inténtao de novo máis tarde.', ASG_DICIONARIO_RAG_TEXT_DOMAIN));
        }
    }
    
    /**
     * Función de desinstalación do plugin
     * 
     * Esta función execútase cando se desinstala o plugin e serve para:
     * - Limpar datos almacenados polo plugin (actualmente non hai ningún)
     * - Eliminar opcións da base de datos se é necesario
     * - Realizar limpeza xeral de recursos do plugin
     * 
     * @static
     */
    public static function asg_uninstall() {
        // Limpar datos do plugin se é necesario
        // (De momento non hai datos que limpar)
        
        // Exemplo para futuras opcións:
        // delete_option('asg_dicionario_rag_opcions');
    }
}

/**
 * Clase para xestionar consultas ao dicionario da Real Academia Galega
 * 
 * Esta clase encapsula toda a lóxica para comunicarse coa API da RAG:
 * - Xestión de peticións HTTP con cURL
 * - Parseado de respostas HTML/JSON
 * - Extracción de datos estruturados
 * - Manexo de autenticación (authToken)
 * - Sanitización de dados recibidos
 */
class ASG_DicionarioRAGConsulta {
    private $baseUrl;
    private $headers;
    private $timeout;

    /**
     * Constructor da clase de consultas ao dicionario
     * 
     * Inicializa as propiedades da clase con valores das constantes
     * definidas anteriormente, configurando:
     * - URL base da RAG
     * - Headers HTTP necesarios para simular un navegador
     * - Timeout para peticións HTTP
     */
    public function __construct() {
        $this->baseUrl = ASG_DICIONARIO_RAG_BASE_URL;
        $this->timeout = ASG_DICIONARIO_RAG_TIMEOUT;
        $this->headers = array(
            'User-Agent: ' . ASG_DICIONARIO_RAG_USER_AGENT,
            'Accept: application/json, text/javascript, */*',
            'Accept-Language: gl-ES,gl;q=0.8,en-US;q=0.5,en;q=0.3',
            'X-Requested-With: XMLHttpRequest',
            'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
            'Origin: https://academia.gal',
            'Connection: keep-alive',
            'Referer: https://academia.gal/dicionario'
        );
    }

    /**
     * Buscar definicións dunha palabra no dicionario da RAG
     * 
     * Esta función realiza unha consulta á RAG para obter as definicións
     * dunha palabra galega específica. O proceso inclúe:
     * - Construción da petición HTTP POST
     * - Envío da consulta á API da RAG
     * - Procesamento da resposta JSON/HTML
     * - Extracción e estruturación das definicións
     * - Manexo de erros e casos excepcionais
     * 
     * @param string $palabra A palabra en galego para buscar
     * @return array|null Array con definicións estruturadas ou null se non se atopa
     * @throws Exception Se hai erros de comunicación coa RAG
     */
    public function asg_buscarPalabra($palabra) {
        $palabra = sanitize_text_field($palabra);
        
        // Log de debug se está activado
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("ASG Dicionario: Buscando palabra - " . $palabra);
        }
        
        // Parámetros da URL para a consulta
        $params = http_build_query(array(
            'p_p_id' => 'com_ideit_ragportal_liferay_dictionary_NormalSearchPortlet',
            'p_p_lifecycle' => '2',
            'p_p_state' => 'normal',
            'p_p_mode' => 'view',
            'p_p_cacheability' => 'cacheLevelPage',
            '_com_ideit_ragportal_liferay_dictionary_NormalSearchPortlet_cmd' => 'cmdNormalSearch',
            '_com_ideit_ragportal_liferay_dictionary_NormalSearchPortlet_renderMode' => 'load',
            '_com_ideit_ragportal_liferay_dictionary_NormalSearchPortlet_nounTitle' => $palabra
        ));

        // Datos do formulario para enviar por POST
        $formData = http_build_query(array(
            '_com_ideit_ragportal_liferay_dictionary_NormalSearchPortlet_fieldSearchNoun' => $palabra
        ));

        // Configurar e executar petición cURL
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $this->baseUrl . '?' . $params,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $formData,
            CURLOPT_HTTPHEADER => $this->headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => $this->timeout
        ));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // Manexo de erros da petición
        if ($error) {
            throw new Exception("Erro cURL: " . $error);
        }

        if ($httpCode !== 200) {
            throw new Exception("Erro HTTP: " . $httpCode);
        }

        if (!$response) {
            return null;
        }

        return $this->asg_parsearResposta($response, $palabra);
    }

    /**
     * Parsear resposta JSON da RAG para extraer datos das definicións
     * 
     * Esta función procesa a resposta JSON que devolve a RAG e extrae
     * a información relevante das definicións. Inclúe:
     * - Decodificación do JSON
     * - Validación da estrutura de datos
     * - Extracción do contido HTML
     * - Delegación ao parseador de HTML
     * 
     * @param string $data Resposta JSON da RAG
     * @param string $palabra Palabra orixinal consultada
     * @return array|null Datos estruturados ou null se non hai contido
     */
    private function asg_parsearResposta($data, $palabra) {
        $json = json_decode($data, true);
        
        if (!$json || !isset($json['items']) || empty($json['items'])) {
            return null;
        }
/*
        $item = $json['items'][0];
        $htmlContent = isset($item['htmlContent']) ? $item['htmlContent'] : '';
        $title = isset($item['title']) ? $item['title'] : $palabra;
        
        if (!$htmlContent) {
            return null;
        }

        return $this->asg_parsearHTML($htmlContent, $title); */
		
    $entradas = array();
    
    foreach ($json['items'] as $item) {
        $htmlContent = isset($item['htmlContent']) ? $item['htmlContent'] : '';
        $title = isset($item['title']) ? $item['title'] : $palabra;
        
        if ($htmlContent) {
            $entrada = $this->asg_parsearHTML($htmlContent, $title);
            if ($entrada) {
                $entradas[] = $entrada;
            }
        }
    }
    
    // Devolver todas as entradas ou null se non hai ningunha
    return !empty($entradas) ? $entradas : null;		
		
		
		
    }

    /**
     * Parsear contido HTML da RAG para extraer definicións estruturadas
     * 
     * Esta función usa DOMDocument e XPath para extraer información
     * estruturada do HTML da RAG, incluíndo:
     * - Lemma principal da palabra
     * - Parte do discurso (substantivo, verbo, etc.)
     * - Definicións numeradas con exemplos
     * - Expresións e frases feitas
     * - Sanitización de todos os datos extraídos
     * 
     * @param string $html Contido HTML da definición
     * @param string $palabra Palabra orixinal
     * @return array Array estruturado con definicións e metadatos
     */
    private function asg_parsearHTML($html, $palabra) {
        // Usar DOMDocument para parsear HTML de forma segura
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);

        // Estrutura base dos datos a devolver
        $entrada = array(
            'palabra' => sanitize_text_field($palabra),
            'parte_discurso' => '',
            'definicions' => array(),
            'expresions' => array()
        );

        // Extraer palabra do span Lemma
        $lemmaNodes = $xpath->query('//span[@class="Lemma__LemmaSign"]');
        if ($lemmaNodes->length > 0) {
            $entrada['palabra'] = sanitize_text_field(trim($lemmaNodes->item(0)->textContent));
        }

        // Extraer parte do discurso
        $posNodes = $xpath->query('//span[@class="Subentry__Part_of_speech"]');
        if ($posNodes->length > 0) {
            $entrada['parte_discurso'] = sanitize_text_field(trim($posNodes->item(0)->textContent));
        }

        // Extraer definicións principais
        $senseNodes = $xpath->query('//span[@class="Sense"]');
        
        foreach ($senseNodes as $sense) {
            $numero = $this->asg_extraerTexto($xpath, './/span[@class="Sense__SenseNumber"]', $sense);
            $definicion = $this->asg_extraerTexto($xpath, './/span[@class="Definition__Definition"]', $sense);
            
            if ($definicion) {
                $ejemplos = array();
                $ejemplosNodes = $xpath->query('.//span[@class="Example__Example"]', $sense);
                foreach ($ejemplosNodes as $ejemplo) {
                    $ejemplos[] = sanitize_text_field(trim($ejemplo->textContent));
                }

                $entrada['definicions'][] = array(
                    'sentido' => sanitize_text_field(trim(str_replace('.', '', $numero))),
                    'definicion' => sanitize_text_field(trim($definicion)),
                    'ejemplos' => $ejemplos
                );
            }
        }

        // Extraer expresións e frases feitas
        $fraseNodes = $xpath->query('//span[@class="Fraseoloxia"]');
        
        foreach ($fraseNodes as $frase) {
            $expresionTexto = $this->asg_extraerTexto($xpath, './/span[@class="Fraseoloxia__Texto"]', $frase);
            
            if ($expresionTexto && !stripos($expresionTexto, 'Palabras relacionadas')) {
                $expresionDefs = array();
                
                // Buscar definicións dentro da expresión
                $senseFraseNodes = $xpath->query('.//span[@class="Sense"]', $frase);
                foreach ($senseFraseNodes as $senseFrase) {
                    $defTexto = $this->asg_extraerTexto($xpath, './/span[@class="Definition__Definition"]', $senseFrase);
                    if ($defTexto) {
                        $expresionDefs[] = array(
                            'definicion' => sanitize_text_field(trim($defTexto))
                        );
                    }
                }
                
                if (!empty($expresionDefs)) {
                    $entrada['expresions'][] = array(
                        'expresion' => sanitize_text_field(trim($expresionTexto)),
                        'definicions' => $expresionDefs
                    );
                }
            }
        }

        return $entrada;
    }

    /**
     * Buscar conxugación completa dun verbo galego na RAG
     * 
     * Esta función realiza o proceso complexo de obter a conxugación
     * verbal completa dun verbo galego, que inclúe:
     * - Obtención do authToken necesario para autenticación
     * - Construción da petición específica para conxugacións
     * - Procesamento da resposta HTML coas táboas de conxugación
     * - Manexo de casos especiais de verbos irregulares
     * 
     * @param string $verbo O verbo en galego para conxugar
     * @return array|null Array con conxugación completa ou null se non se atopa
     * @throws Exception Se hai erros de comunicación ou autenticación
     */
    public function asg_buscarConxugacion($verbo) {
        $verbo = sanitize_text_field($verbo);
        
        // Log de debug se está activado
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("ASG Dicionario: Buscando conxugación - " . $verbo);
        }
        
        // Paso 1: Obter o authToken necesario
        $authToken = $this->asg_obterAuthToken();
        if (!$authToken) {
            throw new Exception("Non se puido obter authToken");
        }
        
        // Paso 2: Construír petición con authToken
        $params = http_build_query(array(
            'p_p_id' => 'com_ideit_ragportal_liferay_dictionary_NormalSearchPortlet',
            'p_p_lifecycle' => '2',
            'p_p_state' => 'normal',
            'p_p_mode' => 'view',
            'p_p_cacheability' => 'cacheLevelPage',
            '_com_ideit_ragportal_liferay_dictionary_NormalSearchPortlet_cmd' => 'cmdConjugateVerb',
            '_com_ideit_ragportal_liferay_dictionary_NormalSearchPortlet_renderMode' => 'load',
            '_com_ideit_ragportal_liferay_dictionary_NormalSearchPortlet_nounTitle' => $verbo
        ));

        $formData = http_build_query(array(
            '_com_ideit_ragportal_liferay_dictionary_NormalSearchPortlet_fieldSearchNoun' => $verbo,
            '_com_ideit_ragportal_liferay_dictionary_NormalSearchPortlet_verb' => '/pc/verbos/' . strtolower($verbo) . '.html',
            'p_auth' => $authToken
        ));

        // Headers específicos para conxugacións
        $headers = array(
            'User-Agent: ' . ASG_DICIONARIO_RAG_USER_AGENT,
            'Accept: application/json, text/javascript, */*',
            'Accept-Language: es-ES,es;q=0.9,gl;q=0.8,en;q=0.7',
            'Accept-Encoding: gzip, deflate, br, zstd',
            'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
            'Origin: https://academia.gal',
            'Referer: https://academia.gal/dicionario/-/termo/' . $verbo,
            'X-Requested-With: XMLHttpRequest',
            'Sec-Fetch-Dest: empty',
            'Sec-Fetch-Mode: cors', 
            'Sec-Fetch-Site: same-origin',
            'Cookie: COOKIE_SUPPORT=true; GUEST_LANGUAGE_ID=gl_ES'
        );

        // Executar petición cURL
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $this->baseUrl . '?' . $params,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $formData,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => $this->timeout
        ));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // Manexo de erros
        if ($error) {
            throw new Exception("Erro cURL: " . $error);
        }

        if ($httpCode !== 200 || !$response) {
            throw new Exception("Erro HTTP: " . $httpCode);
        }

        return $this->asg_parsearConxugacion($response, $verbo);
    }

    /**
     * Obter authToken dinámico da páxina principal da RAG
     * 
     * Esta función visita a páxina principal da RAG e extrae o authToken
     * necesario para realizar consultas de conxugacións. O proceso inclúe:
     * - Petición GET á páxina principal
     * - Busca por patróns de authToken no HTML/JavaScript
     * - Validación do token obtido
     * - Manexo de casos onde non se atopa o token
     * 
     * @return string|null O authToken ou null se non se pode obter
     */
    private function asg_obterAuthToken() {
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => 'https://academia.gal/dicionario',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => ASG_DICIONARIO_RAG_USER_AGENT,
            CURLOPT_TIMEOUT => $this->timeout
        ));
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        // Varios patróns para buscar o authToken no HTML/JS
        $patterns = array(
            '/authToken["\']?\s*:\s*["\']([^"\']+)["\']/',
            '/p_auth["\']?\s*:\s*["\']([^"\']+)["\']/',
            '/"authToken":"([^"]+)"/',
            '/authToken="([^"]+)"/',
            '/Liferay\.authToken\s*=\s*["\']([^"\']+)["\']/'
        );
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $response, $matches)) {
                return $matches[1];
            }
        }
        
        return null;
    }
	
/**
     * Parsear resposta da conxugación verbal da RAG
     * 
     * Esta función procesa a resposta JSON que contén o HTML completo
     * da conxugación verbal, extraendo:
     * - Título da conxugación
     * - HTML completo con todas as táboas de tempos verbais
     * - Metadatos do verbo
     * - Validación da estrutura recibida
     * 
     * @param string $data Resposta JSON da RAG
     * @param string $verbo Verbo orixinal consultado
     * @return array|null Datos da conxugación ou null se non válidos
     */
    private function asg_parsearConxugacion($data, $verbo) {
        $json = json_decode($data, true);
        
        if (!$json || !isset($json['htmlContent'])) {
            return null;
        }
        
        $html = $json['htmlContent'];
        
        // Usar DOMDocument para parsear metadatos
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        
        $conxugacion = array(
            'verbo' => sanitize_text_field($verbo),
            'titulo' => '',
            'html_completo' => $html
        );
        
        // Extraer título da conxugación
        $tituloNodes = $xpath->query('//p[@class="nomverbo"]');
        if ($tituloNodes->length > 0) {
            $conxugacion['titulo'] = sanitize_text_field(trim($tituloNodes->item(0)->textContent));
        }
        
        return $conxugacion;
    }

    /**
     * Extraer texto de nodos DOM usando XPath
     * 
     * Esta función auxiliar simplifica a extracción de texto de nodos DOM
     * usando consultas XPath. É útil para:
     * - Buscar elementos específicos dentro dun contexto
     * - Extraer texto limpo sen tags HTML
     * - Manexar casos onde o elemento non existe
     * - Centralizar a lóxica de extracción de texto
     * 
     * @param DOMXPath $xpath Obxecto XPath para realizar consultas
     * @param string $query Consulta XPath para buscar elementos
     * @param DOMNode|null $context Contexto opcional para limitar a busca
     * @return string Texto extraído ou cadea baleira se non se atopa
     */
    private function asg_extraerTexto($xpath, $query, $context = null) {
        $nodes = $xpath->query($query, $context);
        return $nodes->length > 0 ? trim($nodes->item(0)->textContent) : '';
    }
}

/**
 * Inicialización do plugin
 * 
 * Esta liña crea unha instancia da clase principal do plugin,
 * o que activa todos os hooks e funcionalidades. É o punto
 * de entrada principal do plugin cando WordPress o carga.
 */
new ASG_DicionarioRAG();	