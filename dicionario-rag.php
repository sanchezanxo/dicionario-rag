<?php
/*
Plugin Name: Dicionario RAG - Acceso ao Dicionario da Real Academia Galega
Plugin URI: https://github.com/sanchezanxo/dicionario-rag
Description: Plugin de WordPress que permite acceder ao dicionario oficial da Real Academia Galega vía interface gráfica, ante a ausencia de API pública. Inclúe definicións, conxugacións verbais e funcionalidade completa.
Version: 1.1.0
Author: Anxo Sanchez Garcia
Author URI: https://www.anxosanchez.com
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: dicionario-rag
Requires at least: 5.0
Tested up to: 7.1
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
define('ASG_DICIONARIO_RAG_VERSION', '1.1.0');
define('ASG_DICIONARIO_RAG_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ASG_DICIONARIO_RAG_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('ASG_DICIONARIO_RAG_TEXT_DOMAIN', 'dicionario-rag');

// URLs e configuración da RAG
define('ASG_DICIONARIO_RAG_BASE_URL', 'https://academia.gal/dicionario');
define('ASG_DICIONARIO_RAG_TIMEOUT', 15);
define('ASG_DICIONARIO_RAG_USER_AGENT', 'DicionarioRAG-WordPress/' . ASG_DICIONARIO_RAG_VERSION . ' (+https://github.com/sanchezanxo/dicionario-rag)');

// Caché e límite de peticións á RAG
define('ASG_DICIONARIO_RAG_CACHE_TTL', WEEK_IN_SECONDS);
define('ASG_DICIONARIO_RAG_CACHE_TTL_NON_ATOPADO', DAY_IN_SECONDS);
define('ASG_DICIONARIO_RAG_TOKEN_TTL', 12 * HOUR_IN_SECONDS);
define('ASG_DICIONARIO_RAG_LIMITE_PETICIONS', 30);      // peticións á RAG por IP...
define('ASG_DICIONARIO_RAG_LIMITE_XANELA', 10 * MINUTE_IN_SECONDS); // ...nesta xanela de tempo

/**
 * Pasar a minúsculas respectando os acentos (PÓR -> pór)
 * 
 * WordPress non inclúe polyfill de mb_strtolower, así que se a
 * extensión mbstring non está dispoñible úsase strtolower.
 * 
 * @param string $texto Texto orixinal
 * @return string Texto en minúsculas
 */
function asg_dicionario_rag_minusculas($texto) {
    return function_exists('mb_strtolower') ? mb_strtolower($texto, 'UTF-8') : strtolower($texto);
}

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
     * - Rexistro de assets no frontend (só se cargan se hai shortcode)
     * - Creación do shortcode
     * - Manexo de peticións AJAX (para usuarios logueados e anónimos)
     */
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'asg_rexistrar_assets'));
        add_shortcode('dicionario_rag', array($this, 'asg_mostrar_formulario'));
        add_action('wp_ajax_asg_consultar_rag', array($this, 'asg_manejar_consulta_ajax'));
        add_action('wp_ajax_nopriv_asg_consultar_rag', array($this, 'asg_manejar_consulta_ajax'));
    }
    
    /**
     * Rexistrar os assets (CSS e JavaScript) do plugin
     * 
     * Só se rexistran aquí; cárganse dende o shortcode para non engadir
     * CSS nin JS nas páxinas que non usan o dicionario.
     */
    public function asg_rexistrar_assets() {
        wp_register_style(
            'asg-dicionario-rag-css',
            ASG_DICIONARIO_RAG_PLUGIN_URL . 'assets/css/dicionario-rag.css',
            array(),
            ASG_DICIONARIO_RAG_VERSION
        );
        
        wp_register_script(
            'asg-dicionario-rag-js',
            ASG_DICIONARIO_RAG_PLUGIN_URL . 'assets/js/dicionario-rag.js',
            array('jquery'),
            ASG_DICIONARIO_RAG_VERSION,
            true
        );
        
        // Non se usa nonce: é unha consulta pública de só lectura e os nonces
        // caducan nas páxinas cacheadas. O abuso contrólase co límite por IP.
        wp_localize_script('asg-dicionario-rag-js', 'dicionario_vars', array(
            'ajaxurl' => admin_url('admin-ajax.php')
        ));
    }
    
    /**
     * Render do shortcode [dicionario_rag]
     * 
     * Xera o HTML do formulario de busca. Pode haber varias instancias
     * na mesma páxina, por iso non se usan IDs fixos.
     * 
     * @return string HTML do formulario de busca
     */
    public function asg_mostrar_formulario() {
        static $instancia = 0;
        $instancia++;
        $input_id = 'dicionario-rag-palabra-' . $instancia;
        
        wp_enqueue_style('asg-dicionario-rag-css');
        wp_enqueue_script('asg-dicionario-rag-js');
        
        ob_start();
        ?>
        <div class="dicionario-rag-container">
            <form class="dicionario-rag-form" role="search">
                <label for="<?php echo esc_attr($input_id); ?>">
                    <?php echo esc_html__('Introduce unha palabra en galego:', ASG_DICIONARIO_RAG_TEXT_DOMAIN); ?>
                </label>
                <div class="dicionario-rag-campos">
                    <input 
                        type="text" 
                        id="<?php echo esc_attr($input_id); ?>"
                        class="dicionario-rag-input"
                        placeholder="<?php echo esc_attr__('Exemplo: comer', ASG_DICIONARIO_RAG_TEXT_DOMAIN); ?>" 
                        required 
                        maxlength="100"
                        autocomplete="off"
                    >
                    <button type="submit" class="dicionario-rag-definicion wp-element-button">
                        <?php echo esc_html__('Definición', ASG_DICIONARIO_RAG_TEXT_DOMAIN); ?>
                    </button>
                    <button type="button" class="dicionario-rag-conxugar wp-element-button">
                        <?php echo esc_html__('Conxugación', ASG_DICIONARIO_RAG_TEXT_DOMAIN); ?>
                    </button>
                </div>
            </form>
            
            <div class="dicionario-rag-loading" hidden role="status" aria-live="polite">
                <p><?php echo esc_html__('Consultando o dicionario da RAG…', ASG_DICIONARIO_RAG_TEXT_DOMAIN); ?></p>
            </div>
            
            <div class="dicionario-rag-resultado" role="region" aria-live="polite"></div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Manejar peticións AJAX para consultas ao dicionario
     * 
     * - Valida os datos de entrada
     * - Devolve a resposta da caché se existe
     * - Aplica un límite de peticións por IP antes de consultar á RAG
     * - Delega a consulta á clase ASG_DicionarioRAGConsulta
     */
    public function asg_manejar_consulta_ajax() {
        $palabra = trim(sanitize_text_field(wp_unslash($_POST['palabra'] ?? '')));
        $tipo = sanitize_key(wp_unslash($_POST['tipo'] ?? 'definicion'));
        
        if ($palabra === '') {
            wp_send_json_error(esc_html__('Non se proporcionou ningunha palabra', ASG_DICIONARIO_RAG_TEXT_DOMAIN));
        }
        
        if (mb_strlen($palabra) > 100) {
            wp_send_json_error(esc_html__('A palabra é demasiado longa', ASG_DICIONARIO_RAG_TEXT_DOMAIN));
        }
        
        if (!in_array($tipo, array('definicion', 'conxugacion'), true)) {
            wp_send_json_error(esc_html__('Tipo de consulta non válido', ASG_DICIONARIO_RAG_TEXT_DOMAIN));
        }
        
        // Só letras (con acentos), espazos, guións e apóstrofos.
        // Os verbos teñen que ser unha soa palabra porque van na ruta do ficheiro.
        $patron = $tipo === 'conxugacion' ? '/^[\p{L}\p{M}\-]+$/u' : "/^[\\p{L}\\p{M}\\s'’\\-]+$/u";
        if (!preg_match($patron, $palabra)) {
            wp_send_json_error(esc_html__('A palabra contén caracteres non válidos', ASG_DICIONARIO_RAG_TEXT_DOMAIN));
        }
        
        // Caché: as entradas do dicionario cambian moi pouco
        $clave_cache = 'asg_drag_' . md5(ASG_DICIONARIO_RAG_VERSION . '|' . $tipo . '|' . asg_dicionario_rag_minusculas($palabra));
        $cache = get_transient($clave_cache);
        if ($cache !== false) {
            if ($cache === 'non_atopado') {
                wp_send_json_error(esc_html__('Non se atopou información para esta palabra', ASG_DICIONARIO_RAG_TEXT_DOMAIN));
            }
            wp_send_json_success($cache);
        }
        
        if (!$this->asg_dentro_do_limite()) {
            wp_send_json_error(esc_html__('Demasiadas consultas. Agarda uns minutos e inténtao de novo.', ASG_DICIONARIO_RAG_TEXT_DOMAIN));
        }
        
        $dicionario = new ASG_DicionarioRAGConsulta();
        
        try {
            if ($tipo === 'conxugacion') {
                $resultado = $dicionario->asg_buscarConxugacion($palabra);
            } else {
                $resultado = $dicionario->asg_buscarPalabra($palabra);
            }
        } catch (Exception $e) {
            // Log do erro (só en modo debug)
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('ASG Dicionario RAG Error: ' . $e->getMessage());
            }
            wp_send_json_error(esc_html__('Erro interno. Inténtao de novo máis tarde.', ASG_DICIONARIO_RAG_TEXT_DOMAIN));
        }
        
        if ($resultado) {
            set_transient($clave_cache, $resultado, ASG_DICIONARIO_RAG_CACHE_TTL);
            wp_send_json_success($resultado);
        }
        
        set_transient($clave_cache, 'non_atopado', ASG_DICIONARIO_RAG_CACHE_TTL_NON_ATOPADO);
        wp_send_json_error(esc_html__('Non se atopou información para esta palabra', ASG_DICIONARIO_RAG_TEXT_DOMAIN));
    }
    
    /**
     * Límite de peticións á RAG por IP
     * 
     * Só conta as consultas que non están na caché, é dicir, as que
     * realmente xeran tráfico cara á web da RAG.
     * 
     * @return bool true se a IP aínda pode facer peticións
     */
    private function asg_dentro_do_limite() {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        $clave = 'asg_drag_rl_' . md5($ip);
        $peticions = (int) get_transient($clave);
        
        if ($peticions >= ASG_DICIONARIO_RAG_LIMITE_PETICIONS) {
            return false;
        }
        
        set_transient($clave, $peticions + 1, ASG_DICIONARIO_RAG_LIMITE_XANELA);
        return true;
    }
}

/**
 * Clase para xestionar consultas ao dicionario da Real Academia Galega
 * 
 * Esta clase encapsula toda a lóxica para comunicarse coa web da RAG:
 * - Peticións HTTP coa API HTTP de WordPress
 * - Parseado de respostas HTML/JSON
 * - Extracción de datos estruturados
 * - Manexo de autenticación (authToken)
 * - Sanitización de datos recibidos
 */
class ASG_DicionarioRAGConsulta {
    private $baseUrl;
    private $timeout;
    private $tokenDaCache = false;

    /**
     * Constructor da clase de consultas ao dicionario
     */
    public function __construct() {
        $this->baseUrl = ASG_DICIONARIO_RAG_BASE_URL;
        $this->timeout = ASG_DICIONARIO_RAG_TIMEOUT;
    }

    /**
     * Facer unha petición POST á RAG e devolver o corpo da resposta
     * 
     * Usa a API HTTP de WordPress, que respecta a configuración de proxy
     * do sitio e descomprime as respostas automaticamente.
     * 
     * @param array $params Parámetros da URL (portlet de Liferay)
     * @param array $formData Datos do formulario
     * @param array $headers Headers adicionais
     * @return string Corpo da resposta
     * @throws Exception Se hai erros de comunicación coa RAG
     */
    private function asg_peticion($params, $formData, $headers = array()) {
        $resposta = wp_remote_post($this->baseUrl . '?' . http_build_query($params), array(
            'timeout' => $this->timeout,
            'user-agent' => ASG_DICIONARIO_RAG_USER_AGENT,
            'headers' => array_merge(array(
                'Accept' => 'application/json, text/javascript, */*',
                'Accept-Language' => 'gl-ES,gl;q=0.8',
                'X-Requested-With' => 'XMLHttpRequest',
                'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
                'Origin' => 'https://academia.gal',
                'Referer' => 'https://academia.gal/dicionario',
                'Cookie' => 'COOKIE_SUPPORT=true; GUEST_LANGUAGE_ID=gl_ES'
            ), $headers),
            'body' => $formData
        ));

        if (is_wp_error($resposta)) {
            throw new Exception("Erro HTTP: " . $resposta->get_error_message());
        }

        $httpCode = wp_remote_retrieve_response_code($resposta);
        if ($httpCode !== 200) {
            throw new Exception("Erro HTTP: " . $httpCode);
        }

        return wp_remote_retrieve_body($resposta);
    }

    /**
     * Buscar definicións dunha palabra no dicionario da RAG
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
        
        $response = $this->asg_peticion(
            array(
                'p_p_id' => 'com_ideit_ragportal_liferay_dictionary_NormalSearchPortlet',
                'p_p_lifecycle' => '2',
                'p_p_state' => 'normal',
                'p_p_mode' => 'view',
                'p_p_cacheability' => 'cacheLevelPage',
                '_com_ideit_ragportal_liferay_dictionary_NormalSearchPortlet_cmd' => 'cmdNormalSearch',
                '_com_ideit_ragportal_liferay_dictionary_NormalSearchPortlet_renderMode' => 'load',
                '_com_ideit_ragportal_liferay_dictionary_NormalSearchPortlet_nounTitle' => $palabra
            ),
            array(
                '_com_ideit_ragportal_liferay_dictionary_NormalSearchPortlet_fieldSearchNoun' => $palabra
            )
        );

        if (!$response) {
            return null;
        }

        return $this->asg_parsearResposta($response, $palabra);
    }

    /**
     * Parsear resposta JSON da RAG para extraer datos das definicións
     * 
     * @param string $data Resposta JSON da RAG
     * @param string $palabra Palabra orixinal consultada
     * @return array|null Lista de entradas ou null se non hai contido
     */
    private function asg_parsearResposta($data, $palabra) {
        $json = json_decode($data, true);
        
        if (!$json || !isset($json['items']) || empty($json['items'])) {
            return null;
        }

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
     * Estrutura do HTML da RAG:
     * Lemma > Subentry (unha por categoría gramatical) > Sense > Definition/Example
     * Lemma > Fraseoloxia > Fraseoloxia__Texto + Subentry > Sense | References
     * As entradas sen definición (remisións) teñen References no lugar de Sense.
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
            'subentradas' => array(),
            'expresions' => array()
        );

        // Extraer palabra do span Lemma
        $lemmaNodes = $xpath->query('//span[@class="Lemma__LemmaSign"]');
        if ($lemmaNodes->length > 0) {
            $entrada['palabra'] = sanitize_text_field(trim($lemmaNodes->item(0)->textContent));
        }

        // Subentradas principais (unha por categoría gramatical), fóra da fraseoloxía
        $subentryNodes = $xpath->query('//span[@class="Subentry"][not(ancestor::span[@class="Fraseoloxia"])]');
        foreach ($subentryNodes as $subentry) {
            $subentrada = array(
                'parte_discurso' => sanitize_text_field($this->asg_extraerTexto($xpath, './span[@class="Subentry__Part_of_speech"]', $subentry)),
                'definicions' => $this->asg_extraerSentidos($xpath, $subentry),
                'remisions' => $this->asg_extraerRemisions($xpath, $subentry)
            );
            
            if ($subentrada['definicions'] || $subentrada['remisions']) {
                $entrada['subentradas'][] = $subentrada;
            }
        }

        // Extraer expresións e frases feitas
        $fraseNodes = $xpath->query('//span[@class="Fraseoloxia"]');
        
        foreach ($fraseNodes as $frase) {
            $expresionTexto = $this->asg_extraerTexto($xpath, './/span[@class="Fraseoloxia__Texto"]', $frase);
            
            if ($expresionTexto && stripos($expresionTexto, 'Palabras relacionadas') === false) {
                $expresion = array(
                    'expresion' => sanitize_text_field($expresionTexto),
                    'definicions' => $this->asg_extraerSentidos($xpath, $frase),
                    'remisions' => $this->asg_extraerRemisions($xpath, $frase)
                );
                
                if ($expresion['definicions'] || $expresion['remisions']) {
                    $entrada['expresions'][] = $expresion;
                }
            }
        }

        return $entrada;
    }

    /**
     * Extraer os sentidos (definición + exemplos) dun nodo
     * 
     * @param DOMXPath $xpath Obxecto XPath
     * @param DOMNode $contexto Subentry ou Fraseoloxia
     * @return array Lista de sentidos
     */
    private function asg_extraerSentidos($xpath, $contexto) {
        $sentidos = array();
        
        foreach ($xpath->query('.//span[@class="Sense"]', $contexto) as $sense) {
            $definicion = $this->asg_extraerTexto($xpath, './/span[@class="Definition__Definition"]', $sense);
            if (!$definicion) {
                continue;
            }
            
            $ejemplos = array();
            foreach ($xpath->query('.//span[@class="Example__Example"]', $sense) as $ejemplo) {
                $ejemplos[] = sanitize_text_field(trim($ejemplo->textContent));
            }
            
            $numero = $this->asg_extraerTexto($xpath, './/span[@class="Sense__SenseNumber"]', $sense);
            $sentidos[] = array(
                'sentido' => sanitize_text_field(str_replace('.', '', $numero)),
                'definicion' => sanitize_text_field($definicion),
                'ejemplos' => $ejemplos
            );
        }
        
        return $sentidos;
    }

    /**
     * Extraer remisións a outras entradas ("VÉXASE carón, a")
     * 
     * Só se collen as que non están dentro dun sentido (os sinónimos
     * dos sentidos non se mostran).
     * 
     * @param DOMXPath $xpath Obxecto XPath
     * @param DOMNode $contexto Subentry ou Fraseoloxia
     * @return array Lista de palabras ás que remite
     */
    private function asg_extraerRemisions($xpath, $contexto) {
        $remisions = array();
        $query = './/span[@class="References"][not(ancestor::span[@class="Sense"])]//span[@class="Reference"]';
        
        foreach ($xpath->query($query, $contexto) as $reference) {
            $homonimo = $this->asg_extraerTexto($xpath, './/span[@class="Lemma__HomonymNumber"]', $reference);
            $texto = trim($reference->textContent);
            if ($homonimo !== '') {
                $texto = trim(substr($texto, 0, -strlen($homonimo))) . ' (' . $homonimo . ')';
            }
            $remisions[] = sanitize_text_field($texto);
        }
        
        return $remisions;
    }

    /**
     * Buscar conxugación completa dun verbo galego na RAG
     * 
     * @param string $verbo O verbo en galego para conxugar
     * @return array|null Array con conxugación completa ou null se non se atopa
     * @throws Exception Se hai erros de comunicación ou autenticación
     */
    public function asg_buscarConxugacion($verbo) {
        $verbo = asg_dicionario_rag_minusculas(sanitize_text_field($verbo));
        
        // Log de debug se está activado
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("ASG Dicionario: Buscando conxugación - " . $verbo);
        }
        
        // Se falla co token da caché (pode estar caducado), repítese cun novo
        $resultado = $this->asg_pedirConxugacion($verbo, $this->asg_obterAuthToken());
        if (!$resultado && $this->tokenDaCache) {
            $resultado = $this->asg_pedirConxugacion($verbo, $this->asg_obterAuthToken(true));
        }
        
        return $resultado;
    }

    /**
     * Petición da conxugación dun verbo cun authToken concreto
     * 
     * @param string $verbo Verbo en minúsculas
     * @param string $authToken Token de Liferay (p_auth)
     * @return array|null Datos da conxugación ou null se non se atopa
     * @throws Exception Se hai erros de comunicación coa RAG
     */
    private function asg_pedirConxugacion($verbo, $authToken) {
        $response = $this->asg_peticion(
            array(
                'p_p_id' => 'com_ideit_ragportal_liferay_dictionary_NormalSearchPortlet',
                'p_p_lifecycle' => '2',
                'p_p_state' => 'normal',
                'p_p_mode' => 'view',
                'p_p_cacheability' => 'cacheLevelPage',
                '_com_ideit_ragportal_liferay_dictionary_NormalSearchPortlet_cmd' => 'cmdConjugateVerb',
                '_com_ideit_ragportal_liferay_dictionary_NormalSearchPortlet_renderMode' => 'load',
                '_com_ideit_ragportal_liferay_dictionary_NormalSearchPortlet_nounTitle' => $verbo
            ),
            array(
                '_com_ideit_ragportal_liferay_dictionary_NormalSearchPortlet_fieldSearchNoun' => $verbo,
                '_com_ideit_ragportal_liferay_dictionary_NormalSearchPortlet_verb' => '/pc/verbos/' . $verbo . '.html',
                'p_auth' => $authToken
            ),
            array(
                'Referer' => 'https://academia.gal/dicionario/-/termo/' . rawurlencode($verbo)
            )
        );

        if (!$response) {
            return null;
        }

        return $this->asg_parsearConxugacion($response, $verbo);
    }

    /**
     * Obter o authToken de Liferay da páxina principal da RAG
     * 
     * Gárdase na caché para non descargar a páxina principal en cada
     * consulta. Se non se atopa, devólvese cadea baleira (hoxe a RAG
     * non o valida).
     * 
     * @param bool $renovar Ignorar a caché e pedir un token novo
     * @return string O authToken ou cadea baleira
     */
    private function asg_obterAuthToken($renovar = false) {
        $token = $renovar ? false : get_transient('asg_drag_auth_token');
        $this->tokenDaCache = ($token !== false);
        if ($token !== false) {
            return $token;
        }

        $resposta = wp_remote_get('https://academia.gal/dicionario', array(
            'timeout' => $this->timeout,
            'user-agent' => ASG_DICIONARIO_RAG_USER_AGENT
        ));
        $html = is_wp_error($resposta) ? '' : wp_remote_retrieve_body($resposta);
        
        // Varios patróns para buscar o authToken no HTML/JS
        $patterns = array(
            '/authToken["\']?\s*:\s*["\']([^"\']+)["\']/',
            '/p_auth["\']?\s*:\s*["\']([^"\']+)["\']/',
            '/"authToken":"([^"]+)"/',
            '/authToken="([^"]+)"/',
            '/Liferay\.authToken\s*=\s*["\']([^"\']+)["\']/'
        );
        
        $token = '';
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $token = $matches[1];
                break;
            }
        }
        
        if ($token !== '') {
            set_transient('asg_drag_auth_token', $token, ASG_DICIONARIO_RAG_TOKEN_TTL);
        }
        
        return $token;
    }
	
    /**
     * Parsear resposta da conxugación verbal da RAG
     * 
     * O HTML da RAG fíltrase con wp_kses para deixar só as etiquetas e
     * clases necesarias para as táboas (sen scripts, eventos nin estilos).
     * 
     * @param string $data Resposta JSON da RAG
     * @param string $verbo Verbo orixinal consultado
     * @return array|null Datos da conxugación ou null se non válidos
     */
    private function asg_parsearConxugacion($data, $verbo) {
        $json = json_decode($data, true);
        
        if (!$json || empty($json['htmlContent'])) {
            return null;
        }
        
        $html = wp_kses($json['htmlContent'], self::asg_etiquetasConxugacion());
        
        // Usar DOMDocument para parsear metadatos
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        
        // Sen táboas non hai conxugación (verbo inexistente)
        if ($xpath->query('//table')->length === 0) {
            return null;
        }
        
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
     * Etiquetas e atributos permitidos no HTML da conxugación
     * 
     * @return array Lista para wp_kses
     */
    private static function asg_etiquetasConxugacion() {
        $attr = array('class' => true);
        $celas = array('class' => true, 'colspan' => true, 'rowspan' => true);
        return array(
            'div' => $attr,
            'p' => $attr,
            'span' => $attr,
            'b' => $attr,
            'strong' => $attr,
            'table' => $attr,
            'caption' => $attr,
            'thead' => $attr,
            'tbody' => $attr,
            'tr' => $attr,
            'th' => $celas,
            'td' => $celas
        );
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