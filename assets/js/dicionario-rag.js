jQuery(function($) {
    
    // Cada shortcode da páxina funciona de forma independente
    $('.dicionario-rag-container').each(function() {
        const $container = $(this);
        const $input = $container.find('.dicionario-rag-input');
        const $botons = $container.find('button');
        const $loading = $container.find('.dicionario-rag-loading');
        const $resultado = $container.find('.dicionario-rag-resultado');
        
        // Botón definición (envío do formulario, tamén con Enter)
        $container.find('.dicionario-rag-form').on('submit', function(e) {
            e.preventDefault();
            asg_consultarRAG('definicion', 'Por favor, introduce unha palabra');
        });
        
        // Botón conxugación
        $container.find('.dicionario-rag-conxugar').on('click', function(e) {
            e.preventDefault();
            asg_consultarRAG('conxugacion', 'Por favor, introduce un verbo');
        });
        
        // Consultar o dicionario da RAG
        function asg_consultarRAG(tipo, mensaxeBaleiro) {
            const palabra = $input.val().trim();
            
            if (!palabra) {
                asg_mostrarError(mensaxeBaleiro);
                return;
            }
            
            $loading.prop('hidden', false);
            $resultado.html('');
            $botons.prop('disabled', true);
            
            $.ajax({
                url: dicionario_vars.ajaxurl,
                type: 'POST',
                data: {
                    action: 'asg_consultar_rag',
                    palabra: palabra,
                    tipo: tipo
                },
                success: function(response) {
                    if (response.success) {
                        asg_mostrarResultado(response.data);
                    } else {
                        asg_mostrarError(response.data || 'Non se puido consultar o dicionario');
                    }
                },
                error: function() {
                    asg_mostrarError('Erro de conexión. Inténtao de novo.');
                },
                complete: function() {
                    $loading.prop('hidden', true);
                    $botons.prop('disabled', false);
                }
            });
        }
        
        // Mostrar resultado das consultas
        function asg_mostrarResultado(data) {
            let html = '<div class="resultado-exitoso">';
            
            if (data.html_completo) {
                // === FORMATO PARA CONXUGACIÓNS ===
                html += '<h2 class="palabra-titulo">' + asg_escapeHtml(data.verbo) + '</h2>';
                if (data.titulo) {
                    html += '<h3>' + asg_escapeHtml(data.titulo) + '</h3>';
                }
                
                // O HTML xa vén filtrado (wp_kses) dende o servidor
                html += '<div class="conxugacion-container">';
                html += asg_limparConxugacion(data.html_completo);
                html += '</div>';
                
                html += '<p class="fonte">';
                html += 'Conxugación completa do verbo "' + asg_escapeHtml(data.verbo) + '" obtida da Real Academia Galega';
                html += '</p>';
                
            } else {
                // === FORMATO PARA DEFINICIÓNS ===
                let entradas = Array.isArray(data) ? data : [data];
                
                entradas.forEach(function(entrada, index) {
                    // Título da palabra
                    if (entrada.palabra) {
                        let palabra = asg_escapeHtml(entrada.palabra);
                        
                        // Se a palabra xa ten número ao final (vivir1), reformateala
                        if (/\d+$/.test(palabra)) {
                            palabra = palabra.replace(/(\d+)$/, ' ($1)');
                        }
                        
                        html += '<h2 class="palabra-titulo">' + palabra + '</h2>';
                    }
                    // Unha subentrada por categoría gramatical
                    (entrada.subentradas || []).forEach(function(sub) {
                        if (sub.parte_discurso) {
                            html += '<div class="parte-discurso">' + asg_escapeHtml(sub.parte_discurso) + '</div>';
                        }
                        
                        sub.definicions.forEach(function(def) {
                            html += '<div class="definicion">';
                            
                            if (def.sentido) {
                                html += '<span class="sentido">' + asg_escapeHtml(def.sentido) + '. </span>';
                            }
                            
                            html += '<div class="texto-definicion">' + asg_escapeHtml(def.definicion) + '</div>';
                            
                            // Exemplos
                            if (def.ejemplos && def.ejemplos.length > 0) {
                                html += '<div class="ejemplos">';
                                html += '<strong>Exemplos:</strong>';
                                def.ejemplos.forEach(function(ejemplo) {
                                    html += '<div class="ejemplo">' + asg_escapeHtml(ejemplo) + '</div>';
                                });
                                html += '</div>';
                            }
                            
                            html += '</div>';
                        });
                        
                        html += asg_htmlRemisions(sub.remisions);
                    });
                    
                    // Expresións
                    if (entrada.expresions && entrada.expresions.length > 0) {
                        html += '<div class="expresions">';
                        html += '<h3>Expresións e frases</h3>';
                        
                        entrada.expresions.forEach(function(exp) {
                            html += '<div class="expresion">';
                            html += '<div class="expresion-titulo">' + asg_escapeHtml(exp.expresion) + '</div>';
                            
                            exp.definicions.forEach(function(def) {
                                html += '<div class="texto-definicion">' + asg_escapeHtml(def.definicion) + '</div>';
                            });
                            html += asg_htmlRemisions(exp.remisions);
                            
                            html += '</div>';
                        });
                        
                        html += '</div>';
                    }
                    
                    // Separador entre entradas (se hai máis dunha)
                    if (index < entradas.length - 1) {
                        html += '<hr class="separador-entradas">';
                    }
                });
                
                // Se non hai definicións nin expresións en ningunha entrada
                let hayContido = entradas.some(e => (e.subentradas && e.subentradas.length > 0) || (e.expresions && e.expresions.length > 0));
                
                if (!hayContido) {
                    html += '<div class="sin-resultados">';
                    html += 'Atopouse a palabra pero non se puideron extraer as definicións.';
                    html += '</div>';
                }
            }
            
            html += '</div>';
            
            $resultado.html(html);
        }
        
        // Remisións a outras entradas ("Véxase: carón, a")
        function asg_htmlRemisions(remisions) {
            if (!remisions || remisions.length === 0) return '';
            return '<div class="remision">Véxase: ' + remisions.map(asg_escapeHtml).join(', ') + '</div>';
        }
        
        // Mostrar mensaxe de erro
        function asg_mostrarError(mensaxe) {
            $resultado.html('<div class="error">' + asg_escapeHtml(mensaxe) + '</div>');
        }
    });
    
    // Función para escapar HTML e evitar XSS
    function asg_escapeHtml(text) {
        if (!text) return '';
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
});

// Función para limpar e mellorar o HTML da conxugación
function asg_limparConxugacion(htmlCompleto) {
    // Crear un elemento temporal para manipular o HTML
    let tempDiv = document.createElement('div');
    tempDiv.innerHTML = htmlCompleto
        .replace(/\\/g, '') // Quitar escapes
        .replace(/\r\n/g, '') // Quitar saltos de liña
        .replace(/\s+/g, ' '); // Normalizar espacios
    
    // Cambiar nomes dos tempos verbais
    let textosACambiar = {
        'P.Pluscuamperfecto': 'Antepretérito',
        'Pretérito imperfecto': 'Copretérito', 
        'Condicional': 'Pospretérito',
        'Pretérito Perfecto': 'Pretérito',
        'Infinitivo Conx.': 'Infinitivo conxugado'
    };
    
    // Buscar e cambiar os textos nos th
    let headers = tempDiv.querySelectorAll('th');
    headers.forEach(function(th) {
        let texto = th.textContent.trim();
        if (textosACambiar[texto]) {
            th.textContent = textosACambiar[texto];
        }
    });
    
    // Engadir pronomes ás celas baleiras - USANDO CLASES CSS
    let pronomes = ['eu', 'ti', 'el/ela', 'nós', 'vós', 'eles/elas'];
    let pronomesImperativo = ['—', 'ti', '—', '—', 'vós', '—'];
    
    let etiquetasParticipio = ['masc. sing.', 'fem. sing.', 'masc. pl.', 'fem. pl.'];
    
    // O contador reiníciase en cada táboa; o xerundio non leva pronomes
    tempDiv.querySelectorAll('table').forEach(function(tabla) {
        let caption = tabla.querySelector('caption');
        let textoCaption = caption ? caption.textContent : '';
        
        let arrayPronomes = pronomes;
        if (textoCaption.includes('Imperativo')) {
            arrayPronomes = pronomesImperativo;
        } else if (textoCaption.includes('Participio')) {
            arrayPronomes = etiquetasParticipio;
        } else if (textoCaption.includes('Xerundio')) {
            return;
        }
        
        let contadorPronomes = 0;
        tabla.querySelectorAll('td.anchouno, td.anchocuatro').forEach(function(cela) {
            if (cela.textContent.trim() === '') {
                // Engadir pronome e CLASE CSS no lugar de estilos inline
                cela.textContent = arrayPronomes[contadorPronomes % arrayPronomes.length];
                cela.classList.add('js-pronome-engadido');
                contadorPronomes++;
            }
        });
    });
    
    // Dividir táboas do indicativo e subxuntivo + mellorar outras
    let taboas = tempDiv.querySelectorAll('table');
    
    taboas.forEach(function(tabla, index) {
        let caption = tabla.querySelector('caption');
        
        if (caption && caption.textContent.includes('Indicativo')) {
            asg_dividirTaboaIndicativo(tabla);
        } else if (caption && caption.textContent.includes('Subxuntivo')) {
            asg_dividirTaboaSubxuntivo(tabla);
        } else {
            // Engadir clase específica para outras formas
            tabla.classList.add('tabla-outras-formas');
        }
    });
    
    // Cambiar a clase do container .coldereita
    let coldereita = tempDiv.querySelector('.coldereita');
    if (coldereita) {
        coldereita.classList.add('outras-formas-grid');
    }
    
    return tempDiv.innerHTML;
}

// Función para dividir a táboa do indicativo en táboas separadas
function asg_dividirTaboaIndicativo(tablaIndicativo) {
    let filas = Array.from(tablaIndicativo.querySelectorAll('tr'));
    
    if (filas.length < 2) {
        return;
    }
    
    // Buscar todas as filas de headers
    let filasHeaders = [];
    let temposVerbais = ['Presente', 'Antepretérito', 'Copretérito', 'Futuro', 'Pretérito'];
    
    for (let i = 0; i < filas.length; i++) {
        let celdas = Array.from(filas[i].querySelectorAll('td, th'));
        let contemTempos = celdas.some(c => temposVerbais.some(t => c.textContent.includes(t)));
        
        if (celdas.length >= 3 && contemTempos) {
            filasHeaders.push({
                index: i,
                celdas: celdas,
                tempos: celdas.map(c => c.textContent.trim()).filter(t => t && t.length > 2)
            });
        }
    }
    
    if (filasHeaders.length === 0) {
        return;
    }
    
    // Crear container para as novas táboas
    let containerDiv = document.createElement('div');
    containerDiv.className = 'indicativo-responsive-container';
    
    // Procesar cada fila de headers
    filasHeaders.forEach(function(headerInfo, sectionIndex) {
        let indexFilaHeaders = headerInfo.index;
        let headers = headerInfo.celdas;
        
        // Determinar onde rematan os datos desta sección
        let filaFin = (sectionIndex + 1 < filasHeaders.length) ? 
                     filasHeaders[sectionIndex + 1].index : 
                     filas.length;
        
        // Para cada tempo (columna) desta sección
        for (let colIndex = 1; colIndex < headers.length; colIndex++) {
            let tempoHeader = headers[colIndex];
            let tempoNome = tempoHeader.textContent.trim();
            
            // Saltar columnas baleiras ou separadores
            if (!tempoNome || tempoNome.length < 3) continue;
            
            // Crear nova táboa para este tempo
            let novaTabla = document.createElement('table');
            novaTabla.className = 'tempo-individual';
            
            // Caption co nome do tempo
            let caption = document.createElement('caption');
            caption.textContent = 'Indicativo - ' + tempoNome;
            novaTabla.appendChild(caption);
            
            // Crear tbody
            let tbody = document.createElement('tbody');
            
            // Para cada fila de datos desta sección
            for (let rowIndex = indexFilaHeaders + 1; rowIndex < filaFin; rowIndex++) {
                let filaOrixinal = filas[rowIndex];
                let celdas = filaOrixinal.querySelectorAll('td');
                
                // Saltar filas baleiras ou separadores
                if (celdas.length <= colIndex || !celdas[colIndex].textContent.trim()) continue;
                
                let novaFila = document.createElement('tr');
                
                // Pronome (primeira columna)
                let celdaPronome = celdas[0].cloneNode(true);
                novaFila.appendChild(celdaPronome);
                
                // Forma verbal (columna correspondente)
                let celdaVerbo = celdas[colIndex].cloneNode(true);
                novaFila.appendChild(celdaVerbo);
                
                tbody.appendChild(novaFila);
            }
            
            // Só engadir a táboa se ten datos
            if (tbody.children.length > 0) {
                novaTabla.appendChild(tbody);
                containerDiv.appendChild(novaTabla);
            }
        }
    });
    
    // Substituír a táboa orixinal co novo container
    tablaIndicativo.parentNode.replaceChild(containerDiv, tablaIndicativo);
}

// Función para dividir a táboa do subxuntivo en táboas separadas
function asg_dividirTaboaSubxuntivo(tablaSubxuntivo) {
    let filas = Array.from(tablaSubxuntivo.querySelectorAll('tr'));
    
    if (filas.length < 2) {
        return;
    }
    
    // Buscar todas as filas de headers do subxuntivo
    let filasHeaders = [];
    let temposVerbais = ['Presente', 'Copretérito', 'Futuro'];
    
    for (let i = 0; i < filas.length; i++) {
        let celdas = Array.from(filas[i].querySelectorAll('td, th'));
        let contemTempos = celdas.some(c => temposVerbais.some(t => c.textContent.includes(t)));
        
        if (celdas.length >= 2 && contemTempos) {
            filasHeaders.push({
                index: i,
                celdas: celdas,
                tempos: celdas.map(c => c.textContent.trim()).filter(t => t && t.length > 2)
            });
        }
    }
    
    if (filasHeaders.length === 0) {
        return;
    }
    
    // Crear container para as novas táboas do subxuntivo
    let containerDiv = document.createElement('div');
    containerDiv.className = 'subxuntivo-responsive-container';
    
    // Procesar cada fila de headers do subxuntivo
    filasHeaders.forEach(function(headerInfo, sectionIndex) {
        let indexFilaHeaders = headerInfo.index;
        let headers = headerInfo.celdas;
        
        // Determinar onde rematan os datos desta sección
        let filaFin = (sectionIndex + 1 < filasHeaders.length) ? 
                     filasHeaders[sectionIndex + 1].index : 
                     filas.length;
        
        // Para cada tempo (columna) desta sección
        for (let colIndex = 1; colIndex < headers.length; colIndex++) {
            let tempoHeader = headers[colIndex];
            let tempoNome = tempoHeader.textContent.trim();
            
            // Saltar columnas baleiras ou separadores
            if (!tempoNome || tempoNome.length < 3) continue;
            
            // Crear nova táboa para este tempo
            let novaTabla = document.createElement('table');
            novaTabla.className = 'tempo-individual';
            
            // Caption co nome do tempo
            let caption = document.createElement('caption');
            caption.textContent = 'Subxuntivo - ' + tempoNome;
            novaTabla.appendChild(caption);
            
            // Crear tbody
            let tbody = document.createElement('tbody');
            
            // Para cada fila de datos desta sección
            for (let rowIndex = indexFilaHeaders + 1; rowIndex < filaFin; rowIndex++) {
                let filaOrixinal = filas[rowIndex];
                let celdas = filaOrixinal.querySelectorAll('td');
                
                // Saltar filas baleiras ou separadores
                if (celdas.length <= colIndex || !celdas[colIndex].textContent.trim()) continue;
                
                let novaFila = document.createElement('tr');
                
                // Pronome (primeira columna)
                let celdaPronome = celdas[0].cloneNode(true);
                novaFila.appendChild(celdaPronome);
                
                // Forma verbal (columna correspondente)
                let celdaVerbo = celdas[colIndex].cloneNode(true);
                novaFila.appendChild(celdaVerbo);
                
                tbody.appendChild(novaFila);
            }
            
            // Só engadir a táboa se ten datos
            if (tbody.children.length > 0) {
                novaTabla.appendChild(tbody);
                containerDiv.appendChild(novaTabla);
            }
        }
    });
    
    // Substituír a táboa orixinal co novo container
    tablaSubxuntivo.parentNode.replaceChild(containerDiv, tablaSubxuntivo);
}