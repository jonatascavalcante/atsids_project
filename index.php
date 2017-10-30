<?php 

    class Recurso {
        public $identificador;
        public $disponibilidade;
        public $codigo;
        public $descricao;
        public $codigo_unidade_servico;
        public $descricao_unidade_servico;
        public $placa_meio_material;
        public $prefixo;
    }

    function start_db_connection() {
        $servername = "localhost";
        $username = "root";
        $password = "papa";

        //codigo para obter acesso ao banco de dados
        try {
            $GLOBALS['conn'] = new PDO("mysql:host=$servername;dbname=at_sids", $username, $password);
            // set the PDO error mode to exception
            $GLOBALS['conn']->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            // echo "Connected successfully" . "\n"; 
        } catch(PDOException $e) {
            echo "Connection failed: " . $e->getMessage();
        }
    }//end function

    function request_resource($x_scroll_context, $req_url, $resource_desc) {
        $curl = curl_init();  
        //dados essenciais da requisicao
        $req_header = array(
            'X-Authentication-Token : TRtoken4',
            'X-API-Version : 1'
        );
        //se tiver scroll, vai inserir scroll pra pegar restante da requisicao
        if($x_scroll_context != '') {
            array_push($req_header, 'X-Scroll-Context : ' . $x_scroll_context);
        }

        //Parametros pra fazer funcionar o CURL
        curl_setopt_array($curl, array(
            CURLOPT_HTTPHEADER => $req_header,
            CURLOPT_URL => $req_url,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_VERBOSE => 1,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_HEADER => 1
        ));

        $res_string = curl_exec($curl); //Recebe o resultado da requisicao
        $res_header = get_headers_from_curl_response($res_string); //funcao para pegar o cabeçalho da resposta
        $x_scroll_context = $res_header['X-Scroll-Context']; //salva o scroll_context (se existir) em uma variavel
        if (strpos($res_string, "\r\n\r\n") > 0) {
            //separa o cabeçalho do corpo da requisição
            list($res_aux, $body) = explode("\r\n\r\n", $res_string); //A funcao LIST armazena a primeira parte em res_aux e a segunda em body. Essas partes sao divididas pela funcao EXPLODE, que divide a resposta em cabecalho e body.

            //transforma os dados do corpo da resposta do XML para o formato JSON
            $body_xml = simplexml_load_string($body);
            $body_json = json_encode($body_xml);
            $dados_body_json = json_decode($body_json);

            //realiza o tratamento da resposta da requisicao
            treates_requests($resource_desc, $res_header, $dados_body_json);
        } else {
            echo "Deu ruim";
        }

        curl_close($curl);
        if($x_scroll_context == '') {
            if($resource_desc == "recursos_ativos") {
                // filter_resources();
                print_resources();
            } else if($resource_desc == "chamadas_ativas") {
                filter_calls();
            }
            return;
        }
        request_resource($x_scroll_context, $req_url, $resource_desc);
    }//end function
  
    function get_headers_from_curl_response($response)  {
        $headers = array();

        $header_text = substr($response, 0, strpos($response, "\r\n\r\n"));

        foreach (explode("\r\n", $header_text) as $i => $line)
            if ($i === 0)
                $headers['http_code'] = $line;
            else {
                list ($key, $value) = explode(': ', $line);
                $headers[$key] = $value;
            }//end foreach

        return $headers;
    }//end function

    function treates_requests($resource_desc, $res_header, $dados_body_json) {
        switch($resource_desc) {
            case "id_versao":
                //salva o valor do id da versao atual dos dados auxiliares
                $GLOBALS['id_versao'] = $res_header['X-Id-Versao-Atual-Dados-Auxiliares'];
                break;   
            
            case "situacao_recurso":
                foreach ($dados_body_json->dadosAuxiliares->dadoAuxiliarV1 as $situacao_recurso) {
                    $sql = "INSERT INTO situacoes_recursos (codigo, descricao) 
                        VALUES ('" . $situacao_recurso->codigo . "', '" . $situacao_recurso->descricao . "')";
                    $GLOBALS['conn']->query($sql);
                }                    
                break;
            
            case "tipo_recurso":
                //insere cada tipo de recurso dos bombeiros no banco
                foreach ($dados_body_json->dadosAuxiliares->dadoAuxiliarV1 as $tipo_recurso) {
                    if($tipo_recurso->idOrgao == 2) {
                        $sql = "INSERT INTO tipos_recursos (id, descricao, codigo) 
                            VALUES ('" . $tipo_recurso->id . "', '" . $tipo_recurso->descricao . "', '" . $tipo_recurso->codigo . "')";
                        $GLOBALS['conn']->query($sql);
                    }
                }
                break;

            case "unidade_servico":
                //insere cada unidade de servico dos bombeiros no banco
                foreach ($dados_body_json->dadosAuxiliares->dadoAuxiliarV1 as $unidade_servico) {
                    if($unidade_servico->idOrgao == 2) {
                        $sql = "INSERT INTO unidades_servico (id, descricao, codigo) 
                            VALUES ('" . $unidade_servico->id . "', '" . $unidade_servico->descricao . "', '" . $unidade_servico->codigo . "')";
                        $GLOBALS['conn']->query($sql);
                    }
                }
                break;
            
            case "recursos_ativos":
                //insere todos os recursos ativos em um array
                foreach ($dados_body_json->dadosVigentesRecursos->dadosVigentesRecursoV1 as $recurso) {
                    array_push($GLOBALS['recursos'], $recurso);
                }
                break;

            case "origem_chamada":
                //insere cada unidade de servico dos bombeiros no banco
                foreach ($dados_body_json->dadosAuxiliares->dadoAuxiliarV1 as $origem_chamada) {
                    $sql = "INSERT INTO origem_chamada (codigo, descricao) 
                        VALUES ('" . $origem_chamada->codigo . "', '" . $origem_chamada->descricao . "')";
                    $GLOBALS['conn']->query($sql);
                }
                break;

            case "prioridade":
                //insere cada unidade de servico dos bombeiros no banco
                foreach ($dados_body_json->dadosAuxiliares->dadoAuxiliarV1 as $prioridade) {
                    $sql = "INSERT INTO prioridade (codigo, descricao) 
                        VALUES ('" . $prioridade->codigo . "', '" . $prioridade->descricao . "')";
                    $GLOBALS['conn']->query($sql);
                }
                break;

            case "situacao_atendimento_chamada":
                //insere cada unidade de servico dos bombeiros no banco
                foreach ($dados_body_json->dadosAuxiliares->dadoAuxiliarV1 as $situacao_atendimento_chamada) {
                    $sql = "INSERT INTO situacao_atendimento_chamada (codigo, descricao) 
                        VALUES ('" . $situacao_atendimento_chamada->codigo . "', '" . $situacao_atendimento_chamada->descricao . "')";
                    $GLOBALS['conn']->query($sql);
                }
                break;

            case "situacao_chamada":
                //insere cada unidade de servico dos bombeiros no banco
                foreach ($dados_body_json->dadosAuxiliares->dadoAuxiliarV1 as $situacao_chamada) {
                    $sql = "INSERT INTO situacao_chamada (codigo, descricao) 
                        VALUES ('" . $situacao_chamada->codigo . "', '" . $situacao_chamada->descricao . "')";
                    $GLOBALS['conn']->query($sql);
                }
                break;

            case "chamadas_ativas":
                //insere todos os recursos ativos em um array
                foreach ($dados_body_json->dadosVigentesChamadas->dadosVigentesChamadaV1 as $chamada) {
                    array_push($GLOBALS['chamadas'], $chamada);
                }
                break;
        }//end switch
    }//end function

    function filter_resources() {
        foreach ($GLOBALS['recursos'] as $recurso) {
            $sql = "SELECT * FROM tipos_recursos WHERE id = '" . $recurso->idTipoRecurso . "'";
            $result = $GLOBALS['conn']->query($sql)->fetch(PDO::FETCH_ASSOC);
            
            if($result['id'] != "") {
                $current_resource = new Recurso();
                $current_resource->identificador = $recurso->id;
                if($recurso->codigoSituacaoRecurso == 'D') {
                    $current_resource->disponibilidade = 'disponível';
                } else if($recurso->codigoSituacaoRecurso == 'E') {
                    $current_resource->disponibilidade = 'empenhado';
                } else {
                    $current_resource->disponibilidade = 'indisponível';
                }
                $current_resource->codigo = $result['codigo'];
                $current_resource->descricao = $result['descricao'];
                
                $sql = "SELECT * FROM unidades_servico WHERE id = '" . $recurso->idUnidadeServico . "'";
                $result = $GLOBALS['conn']->query($sql)->fetch(PDO::FETCH_ASSOC);
                
                $current_resource->codigo_unidade_servico = $result['codigo'];
                $current_resource->descricao_unidade_servico = $result['descricao'];
                $current_resource->placa_meio_material = $recurso->placaMeioMaterial;
                $current_resource->prefixo = $recurso->prefixo;

                array_push($GLOBALS['recursos_retorno'], $current_resource);
            } //end if          
        } //end foreach         
    } //end function

    function print_resources() {
        $qtd_recursos_empenhados = 0;
        $qtd_recursos_disponiveis = 0;
        $recursos_empenhados = array();
        $recursos_disponiveis = array();

        foreach ($GLOBALS['recursos'] as $recurso) {
            $sql = "SELECT * FROM tipos_recursos WHERE id = '" . $recurso->idTipoRecurso . "'";
            $result = $GLOBALS['conn']->query($sql)->fetch(PDO::FETCH_ASSOC);

            if($result['id'] != "") {
                if($recurso->codigoSituacaoRecurso == 'E') {
                    $qtd_recursos_empenhados++;
                    if(array_key_exists($result['descricao'], $recursos_empenhados)) {
                        $recursos_empenhados[$result['descricao']]++;
                    } else {
                        $recursos_empenhados[$result['descricao']] = 1;
                    }
                } else if($recurso->codigoSituacaoRecurso == 'D') {
                    $qtd_recursos_disponiveis++;
                    if(array_key_exists($result['descricao'], $recursos_disponiveis)) {
                        $recursos_disponiveis[$result['descricao']]++;
                    } else {
                        $recursos_disponiveis[$result['descricao']] = 1;
                    }
                }
            }
        }
        echo "<pre>";
        echo "Dados das Viaturas: <br>";
        echo " - " . $qtd_recursos_empenhados . " Viatura(s) Empenhadas<br>";
        echo " - " . $qtd_recursos_disponiveis . " Viatura(s) Disponíveis<br>";
        echo "<br>Tipos de Viaturas Empenhadas: <br>";
        foreach ($recursos_empenhados as $key => $value) {
            echo " - " . $value . " " . $key . "<br>";
        }
        echo "<br>Tipos de Viaturas Disponíveis: <br>";
        foreach ($recursos_disponiveis as $key => $value) {
            echo " - " . $value . " " . $key . "<br>";
        }
        echo "</pre>";
    }

    function filter_calls() {
        $total_chamadas = 0;
        $chamadas_atendidas = 0;
        $chamadas_aguardando = 0;
        $chamadas_destaque = 0;
        foreach ($GLOBALS['chamadas'] as $chamada) {
            $codigo_situacao_atendimento = $chamada->dadosVigentesAtendimentosChamada->dadosVigentesAtendimentoChamadaV1->codigoSituacaoAtendimentoChamada;
            if($codigo_situacao_atendimento != '') {
                $total_chamadas++; 
                if($codigo_situacao_atendimento == 'DP') {
                    $chamadas_atendidas++;
                } else {
                    if($codigo_situacao_atendimento != 'ER' && $codigo_situacao_atendimento != 'EN' && $codigo_situacao_atendimento != 'TR' &&
                            $codigo_situacao_atendimento != 'SU') {
                        $chamadas_aguardando++;
                    }
                }
            }
            if($chamada->dadosVigentesAtendimentosChamada->dadosVigentesAtendimentoChamadaV1->destaque == 'true') {
                $chamadas_destaque++;
            }
        } //end foreach
        echo "<pre>";
        echo "<br>Dados das Chamadas: <br>";
        echo " - Total de ocorrências: " . $total_chamadas . "<br/>"; 
        echo " - Ocorrências sendo atendidas: " . $chamadas_atendidas . "<br/>";
        echo " - Ocorrências aguardando atendimento: " . $chamadas_aguardando . "<br/>";
        echo " - Ocorrências destaque: " . $chamadas_destaque . "<br/>";
        echo "</pre>";
    } //end function

    function update_database() {
        $sql = "DELETE FROM versao_dados_auxiliares";
        $GLOBALS['conn']->query($sql);
        $sql = "INSERT INTO versao_dados_auxiliares(id_versao) 
            VALUES('" . $GLOBALS['id_versao'] . "')";
        $GLOBALS['conn']->query($sql);

        $sql = "DELETE FROM situacoes_recursos";
        $GLOBALS['conn']->query($sql);
        request_resource('', 'https://treinamento.sids.mg.gov.br/cad/brokerLeitura/dadosAuxiliares/' . $GLOBALS['id_versao'] . '/situacaoRecurso', "situacao_recurso");
        
        $sql = "DELETE FROM tipos_recursos";
        $GLOBALS['conn']->query($sql);
        request_resource('', 'https://treinamento.sids.mg.gov.br/cad/brokerLeitura/dadosAuxiliares/' . $GLOBALS['id_versao'] . '/tipoRecurso', "tipo_recurso");
        
        $sql = "DELETE FROM unidades_servico";
        $GLOBALS['conn']->query($sql);
        request_resource('', 'https://treinamento.sids.mg.gov.br/cad/brokerLeitura/dadosAuxiliares/' . $GLOBALS['id_versao'] . '/unidadeServico', "unidade_servico"); 
    
        $sql = "DELETE FROM origem_chamada";
        $GLOBALS['conn']->query($sql);
        request_resource('', 'https://treinamento.sids.mg.gov.br/cad/brokerLeitura/dadosAuxiliares/' . $GLOBALS['id_versao'] . '/origemChamada', "origem_chamada");
        
        $sql = "DELETE FROM prioridade";
        $GLOBALS['conn']->query($sql);
        request_resource('', 'https://treinamento.sids.mg.gov.br/cad/brokerLeitura/dadosAuxiliares/' . $GLOBALS['id_versao'] . '/prioridade', "prioridade");
        
        $sql = "DELETE FROM situacao_atendimento_chamada";
        $GLOBALS['conn']->query($sql);
        request_resource('', 'https://treinamento.sids.mg.gov.br/cad/brokerLeitura/dadosAuxiliares/' . $GLOBALS['id_versao'] . '/situacaoAtendimentoChamada', "situacao_atendimento_chamada");
        
        $sql = "DELETE FROM situacao_chamada";
        $GLOBALS['conn']->query($sql);
        request_resource('', 'https://treinamento.sids.mg.gov.br/cad/brokerLeitura/dadosAuxiliares/' . $GLOBALS['id_versao'] . '/situacaoChamada', "situacao_chamada");       
    }

    function main() {
        $id_corrente;
        start_db_connection();

        $sql = "SELECT id_versao FROM versao_dados_auxiliares";
        $result = $GLOBALS['conn']->query($sql)->fetch(PDO::FETCH_ASSOC);
        $id_corrente = $result['id_versao'];

        request_resource('', 'https://treinamento.sids.mg.gov.br/cad/brokerLeitura/dadosAuxiliares/', 'id_versao');

        //verifica a versao dos dados e faz a atualizacao do banco, caso necessario
        if($id_corrente == '' || $id_corrente != '' && $id_corrente < $GLOBALS['id_versao']) {
            update_database();
        }
        request_resource('', 'https://treinamento.sids.mg.gov.br/cad/brokerLeitura/recursos/ativos/', 'recursos_ativos');
        request_resource('', 'https://treinamento.sids.mg.gov.br/cad/brokerLeitura/chamadas/ativas/?2', 'chamadas_ativas');
    }

    //global variables
    $recursos = array();
    $recursos_retorno = array();
    $chamadas = array();
    $con;
    $id_versao;

    main();

    //Exibe o resultado dos recursos formatados no padrao do array
    // echo "<pre>";
    // print_r($GLOBALS['recursos_retorno']);
    // echo "</pre>";
?>