<?php

use Adianti\Control\TPage;
use Adianti\Widget\Form\TForm;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Form\TButton;
use Adianti\Wrapper\BootstrapFormBuilder;
use Adianti\Widget\Wrapper\TDBCombo;
use Adianti\Database\TTransaction;
use Adianti\Control\TAction;
use App\Service\ConsultaCepService;

class ClienteForm extends TPage
{
    private $form;

    public function __construct()
    {
        parent::__construct();
        
        $this->form = new BootstrapFormBuilder('form_cliente');
        $this->form->addContent( ['<h4>Cadastro de Cliente</h4><hr>'] );
        $this->form->setFieldSizes('100%');
        
        $id        = new TEntry('id');
        $nome      = new TEntry('nome');
        $email     = new TEntry('email');
        $telefone  = new TEntry('telefone');
        $cpf       = new TEntry('cpf');
        
        $logradouro = new TEntry('logradouro');
        $cep     = new TEntry('cep');
        $numero     = new TEntry('numero');
        $bairro     = new TEntry('bairro');
        $cidade     = new TEntry('cidade');
        $estado     = new TEntry('estado');
        $complemento     = new TEntry('complemento');
        
        $id->setEditable(FALSE);
    
        $cpf->setMask('999.999.999-99');
        
        // Organizando os campos no formulário
        $row = $this->form->addFields([new TLabel('ID'), $id],
                                      [new TLabel('Nome'), $nome],
                                      [new TLabel('CPF'), $cpf]);
        $row->layout = ['col-sm-4', 'col-sm-4', 'col-sm-4'];
        $this->form->addContent( ['<h4 style="margin-top: 1%">Contatos do Cliente</h4>'] );
        $row = $this->form->addFields(  [new TLabel('Telefone'), $telefone],
                                        [new TLabel('Email'), $email]);
        $row->layout = ['col-sm-6','col-sm-6'];
    
        $this->form->addContent( ['<h4 style="margin-top: 1%">Endereço do Cliente</h4>'] );
        
        $row = $this->form->addFields([new TLabel('CEP'), $cep],
                                      [new TLabel('Logradouro'), $logradouro],
                                      [new TLabel('Número'), $numero]
                                      );
        $row->layout = ['col-sm-3', 'col-sm-6', 'col-sm-3'];
    
        $row = $this->form->addFields([new TLabel('Cidade'), $cidade],
                                      [new TLabel('Estado'), $estado],
                                      [new TLabel('Bairro'), $bairro]);
        $row->layout = ['col-sm-4', 'col-sm-4', 'col-sm-4'];
        $row = $this->form->addFields([new TLabel('Complemento'), $complemento]);
        $row->layout = ['col-sm-12'];
        $this->form->addAction('Buscar Endereco Via CEP', new TAction([$this, 'onBuscarCep'], ['cep' => $cep]), '');
        $this->form->addAction('Salvar', new TAction([$this, 'onSave']), 'fas:save');
        $this->form->addActionLink('Limpar', new TAction([$this, 'onClear']), 'fas:eraser red');
        
        parent::add($this->form);
    }
    
    public function onEdit($param)
    {
        try {
            TTransaction::open('development');

            if (isset($param['id'])) {
                $cliente = new Cliente($param['id']);
                $this->form->setData($cliente);
                if ($cliente->endereco_id > 0) {
                    $endereco = new Endereco($cliente->endereco_id);
                    
                    if (!$endereco->idEndereco) {
                        throw new Exception('Endereço não encontrado.');
                    }
    
                    $data = (object) array_merge(
                        (array) $cliente->toArray(),
                        (array) $endereco->toArray()
                    );
                    $this->form->setData($data);
                } else {
                    new TMessage('info', 'Cliente não possui endereço cadastrado.');
                }
            } else {
                throw new Exception('Cliente não encontrado.');
            }

            TTransaction::close();
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }


    public function onSave()
    {
        try
        {
            TTransaction::open('development');

            $data = $this->form->getData();

            $clienteExistente = Cliente::where('email', '=', $data->email)->first();

            if ($clienteExistente && $clienteExistente->id != $data->id) {
                throw new Exception('O email já está cadastrado para outro cliente.');
            }
            
            $cliente = new Cliente;
            if ($data->id) {
                $cliente->load($data->id); // Carrega o cliente existente
            }

            $endereco = new Endereco;
            if ($cliente->endereco_id) {
                $endereco->load($cliente->endereco_id); // Carrega o endereço existente
            }
            $endereco->logradouro = $data->logradouro;
            $endereco->numero = $data->numero;
            $endereco->bairro = $data->bairro;
            $endereco->cidade = $data->cidade;
            $endereco->estado = $data->estado;
            $endereco->complemento = $data->complemento;
            $endereco->cep = $data->cep;
            $endereco->store();
            
            $cliente->endereco_id = $endereco->idEndereco;
            $cliente->fromArray((array) $data);
            $cliente->store();
            
            TTransaction::close();
            
            new TMessage('info', 'Cliente e endereço salvos com sucesso!');
            $this->form->clear(); // Limpa o formulário após salvar
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    
    
    public function onClear()
    {
        $this->form->clear();
    }

    public static function onBuscarCep($param) 
    {
        try 
        {
            // Verifica se o CEP foi informado
            if (!isset($param['cep']) || empty($param['cep'])) {
                throw new Exception('Por favor, informe o CEP.');
            }
            
            $endereco = ConsultaCepService::getCep($param['cep'], 'json');
            
            if (isset($endereco->erro)) {
                throw new Exception('CEP não encontrado.');
            }

            // Cria um objeto Endereco para enviar ao formulário
            $object = new stdClass();
            $object->logradouro  = $endereco->logradouro;
            $object->bairro      = $endereco->bairro;
            $object->cidade      = $endereco->localidade;
            $object->estado      = $endereco->uf;
            $object->complemento = $endereco->complemento;
            
            // Envia os dados para o formulário
            TForm::sendData('form_cliente', $object);

        } 
        catch (Exception $e) 
        {
            new TMessage('error', $e->getMessage());
        }
    }

}
