jQuery(document).ready(function($) {
    
    // Manejar envío do formulario
    $('#dicionario-form').on('submit', function(e) {
        e.preventDefault();
        
        const palabra = $('#palabra-input').val().trim();
        
        if (!palabra) {
            alert('Por favor, introduce unha palabra');
            return;
        }
        
        asg_consultarRAG(palabra);
    });
	
	// Botón conxugación
	$('#conxugar-btn').on('click', function(e) {
		e.preventDefault();
		
		const palabra = $('#palabra-input').val().trim();
		
		if (!palabra) {
			alert('Por favor, introduce un verbo');
			return;
		}
		
		asg_consultarRAG(palabra, 'conxugacion');
	});	
		
    // Consultar o dicionario da RAG
    function asg_consultarRAG(palabra, tipo = 'definicion') {
        // Mostrar loading
        $('#loading').show();
        $('#resultado').html('');
        $('#consultar-btn').prop('disabled', true).text('Consultando...');
        
        // Facer petición AJAX
        $.ajax({
            url: dicionario_vars.ajaxurl,
            type: 'POST',
            data: {
                action: 'asg_consultar_rag',
                palabra: palabra,
				tipo: tipo,
                nonce: dicionario_vars.nonce
            },
            success: function(response) {
                if (response.success) {
                    asg_mostrarResultado(response.data);
                } else {
                    asg_mostrarError(response.data || 'Non se puido consultar o dicionario');
                }
            },
            error: function(xhr, status, error) {
                asg_mostrarError('Erro de conexión. Inténtao de novo.');
            },
            complete: function() {
                // Ocultar loading
                $('#loading').hide();
                $('#consultar-btn').prop('disabled', false).text('Consultar');
            }
        });
    }
    
    // Mostrar resultado das consultas
    function asg_mostrarResultado(data) {
        let html = '<div class="resultado-exitoso">';
        
        // DETECTAR SE É CONXUGACIÓN
        if (data.html_completo && data.titulo) {
            // === FORMATO PARA CONXUGACIÓNS ===
            html += '<div class="palabra-titulo">' + asg_escapeHtml(data.verbo) + '</div>';
            html += '<h4>🔄 ' + asg_escapeHtml(data.titulo) + '</h4>';
            
            // Limpar e mellorar o HTML da conxugación
            let htmlLimpo = asg_limparConxugacion(data.html_completo);
            
            // Container da conxugación
            html += '<div class="conxugacion-container">';
            html += htmlLimpo;
            html += '</div>';
            
            html += '<div class="fonte">';
            html += '💡 Conxugación completa do verbo "' + asg_escapeHtml(data.verbo) + '" obtida da Real Academia Galega';
            html += '</div>';
            
		} else {
			// === FORMATO PARA DEFINICIÓNS ===
			
			// Agora data é unha array de entradas
			let entradas = Array.isArray(data) ? data : [data];
			
			entradas.forEach(function(entrada, index) {
				// Título da palabra
				if (entrada.palabra) {
					let palabra = asg_escapeHtml(entrada.palabra);
					
					// Se a palabra xa ten número ao final (vivir1), reformateala
					if (/\d+$/.test(palabra)) {
						palabra = palabra.replace(/(\d+)$/, ' ($1)');
					}
					
					html += '<div class="palabra-titulo">' + palabra + '</div>';
				}
				// Parte do discurso
				if (entrada.parte_discurso) {
					html += '<div class="parte-discurso">' + asg_escapeHtml(entrada.parte_discurso) + '</div>';
				}
				
				// Definicións principais
				if (entrada.definicions && entrada.definicions.length > 0) {
					html += '<h4>📖 Definicións:</h4>';
					
					entrada.definicions.forEach(function(def, index) {
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
								html += '<div class="ejemplo">• ' + asg_escapeHtml(ejemplo) + '</div>';
							});
							html += '</div>';
						}
						
						html += '</div>';
					});
				}
				
				// Expresións
				if (entrada.expresions && entrada.expresions.length > 0) {
					html += '<div class="expresions">';
					html += '<h4>💬 Expresións e frases:</h4>';
					
					entrada.expresions.forEach(function(exp) {
						html += '<div class="expresion">';
						html += '<div class="expresion-titulo">' + asg_escapeHtml(exp.expresion) + '</div>';
						
						if (exp.definicions) {
							exp.definicions.forEach(function(def) {
								html += '<div class="texto-definicion">• ' + asg_escapeHtml(def.definicion) + '</div>';
							});
						}
						
						html += '</div>';
					});
					
					html += '</div>';
				}
				
				// Separador entre entradas (se hai máis dunha)
				if (index < entradas.length - 1) {
					html += '<hr style="margin: 30px 0; border: 1px solid #eee;">';
				}
			});
			
			// Se non hai definicións nin expresións en ningunha entrada
			let hayDefiniciones = entradas.some(e => e.definicions && e.definicions.length > 0);
			let hayExpresiones = entradas.some(e => e.expresions && e.expresions.length > 0);
			
			if (!hayDefiniciones && !hayExpresiones) {
				html += '<div class="sin-resultados">';
				html += '🤔 Atopouse a palabra pero non se puideron extraer as definicións.';
				html += '</div>';
			}
		}
			
		
        
        html += '</div>';
        
        $('#resultado').html(html);
    }

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
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    // Mostrar mensaxe de erro
    function asg_mostrarError(mensaje) {
        const html = '<div class="error">❌ ' + asg_escapeHtml(mensaje) + '</div>';
        $('#resultado').html(html);
    }
    
    // Permitir buscar premendo Enter
    $('#palabra-input').on('keypress', function(e) {
        if (e.which === 13) {
            $('#dicionario-form').submit();
        }
    });
    
    // Auto-focus no input
    $('#palabra-input').focus();
    
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
    
    let celasBaleiras = tempDiv.querySelectorAll('td.anchouno, td.anchocuatro');
    let contadorPronomes = 0;
    
    celasBaleiras.forEach(function(cela) {
        if (cela.textContent.trim() === '') {
            // Verificar se estamos nun imperativo
            let tabla = cela.closest('table');
            let caption = tabla ? tabla.querySelector('caption') : null;
            let isImperativo = caption && caption.textContent.includes('Imperativo');
            
            // Escoller array de pronomes
            let arrayPronomes = isImperativo ? pronomesImperativo : pronomes;
            let indice = contadorPronomes % arrayPronomes.length;
            
            // Engadir pronome e CLASE CSS no lugar de estilos inline
            cela.textContent = arrayPronomes[indice];
            cela.classList.add('js-pronome-engadido');
            
            contadorPronomes++;
        }
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