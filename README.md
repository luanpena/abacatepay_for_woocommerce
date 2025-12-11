# AbacatePay for WooCommerce

Um plugin de pagamento robusto e completo para integrar a AbacatePay com WooCommerce, oferecendo suporte a **PIX** e **Cartão de Crédito**.

## Características

- ✅ Integração completa com a API da AbacatePay
- ✅ Suporte a PIX e Cartão de Crédito
- ✅ Modo de Desenvolvimento (Dev Mode) para testes
- ✅ Webhook Listener para notificações de pagamento
- ✅ Validação de assinatura HMAC-SHA256
- ✅ Gerenciamento de configurações via painel do WooCommerce
- ✅ Suporte a reembolsos
- ✅ Logs detalhados de transações
- ✅ Compatível com WooCommerce 5.0+
- ✅ Requer PHP 7.4+

## Requisitos

- WordPress 5.0 ou superior
- WooCommerce 5.0 ou superior
- PHP 7.4 ou superior
- cURL habilitado no servidor

## Instalação

### 1. Upload do Plugin

1. Faça download do arquivo `abacatepay-woocommerce-plugin.zip`
2. No painel do WordPress, vá para **Plugins > Adicionar Novo**
3. Clique em **Fazer Upload de Plugin**
4. Selecione o arquivo `abacatepay-woocommerce-plugin.zip`
5. Clique em **Instalar Agora**
6. Após a instalação, clique em **Ativar Plugin**

### 2. Configuração

1. No painel do WordPress, vá para **WooCommerce > Configurações**
2. Clique na aba **Pagamentos**
3. Localize **AbacatePay** na lista de métodos de pagamento
4. Clique em **Gerenciar** para abrir as configurações

### 3. Configurar as Chaves de API

1. **Chave de API - Desenvolvimento**: Insira sua chave de API do ambiente de desenvolvimento da AbacatePay
2. **Chave de API - Produção**: Insira sua chave de API do ambiente de produção da AbacatePay
3. **Modo Desenvolvedor**: Marque esta opção para usar o ambiente de desenvolvimento

### 4. Configurar o Webhook

1. Na seção de configurações do AbacatePay, você verá a **URL do Webhook** pré-preenchida
2. Copie esta URL
3. Acesse o painel da AbacatePay em https://dashboard.abacatepay.com
4. Vá para **Configurações > Webhooks**
5. Adicione um novo webhook com a URL fornecida
6. Selecione os eventos que deseja receber:
   - `billing.paid` - Cobrança paga
   - `pix.paid` - PIX pago
   - `pix.expired` - PIX expirado
   - `withdraw.paid` - Saque realizado

### 5. Métodos de Pagamento

1. Selecione quais métodos de pagamento deseja aceitar:
   - **PIX**: Pagamento instantâneo via PIX
   - **Cartão**: Pagamento via cartão de crédito

## Configuração Avançada

### Variáveis de Ambiente

Você pode usar variáveis de ambiente para configurar as chaves de API de forma mais segura:

```php
// Adicione ao seu wp-config.php
define( 'ABACATEPAY_API_KEY_DEV', 'sua-chave-dev-aqui' );
define( 'ABACATEPAY_API_KEY_PROD', 'sua-chave-prod-aqui' );
```

### Filtros Disponíveis

O plugin oferece vários filtros para personalização:

```php
// Alterar o ícone do gateway
add_filter( 'woocommerce_abacatepay_icon', function() {
	return 'https://seu-dominio.com/logo-abacatepay.png';
} );

// Alterar os dados da cobrança antes de enviar para a API
add_filter( 'abacatepay_billing_data', function( $data, $order ) {
	// Customize $data aqui
	return $data;
}, 10, 2 );
```

## Fluxo de Pagamento

### Cartão de Crédito (Síncrono)

1. Cliente seleciona **Cartão de Crédito** no checkout
2. Plugin cria uma cobrança via API da AbacatePay
3. Cliente é redirecionado para a página de pagamento da AbacatePay
4. Após o pagamento, cliente retorna ao site
5. Webhook confirma o pagamento e atualiza o status do pedido

### PIX (Assíncrono)

1. Cliente seleciona **PIX** no checkout
2. Plugin cria um QRCode PIX via API da AbacatePay
3. Cliente escaneia o QRCode e realiza o pagamento
4. Webhook recebe notificação de pagamento
5. Status do pedido é atualizado automaticamente

## Modo de Desenvolvimento

O **Modo de Desenvolvimento** permite testar o plugin sem afetar o ambiente de produção:

- Todas as transações são simuladas
- Webhooks podem ser testados manualmente
- Você pode simular pagamentos de PIX para testes

### Simular Pagamento de PIX

Para simular um pagamento de PIX em modo de desenvolvimento:

```bash
curl --request POST \
  --url 'https://api.abacatepay.com/v1/pixQrCode/simulate-payment?id=pix_char_123456' \
  --header 'authorization: Bearer SEU_TOKEN_AQUI' \
  --header 'content-type: application/json' \
  --data '{
    "metadata": {}
  }'
```

## Logs

Os logs do plugin são salvos em:
- **Local**: `/wp-content/uploads/wc-logs/`
- **Arquivo**: `abacatepay-webhook-YYYY-MM-DD.log`

Para visualizar os logs:
1. No painel do WordPress, vá para **WooCommerce > Status > Logs**
2. Selecione o arquivo de log do AbacatePay

## Troubleshooting

### Erro: "Chave de API não configurada"

**Solução**: Verifique se você inseriu a chave de API nas configurações do plugin.

### Erro: "Webhook signature inválida"

**Solução**: Verifique se a chave de API usada para validar o webhook é a mesma configurada no plugin.

### Erro: "Resposta inválida da API"

**Solução**: Verifique se:
- A chave de API está correta
- O servidor tem acesso à internet
- cURL está habilitado no servidor

### Pedido não atualiza após pagamento

**Solução**: Verifique:
- Se o webhook está configurado corretamente na AbacatePay
- Os logs do plugin para erros
- Se a URL do webhook está acessível publicamente

## Suporte

Para suporte, entre em contato com:
- **Email**: ajuda@abacatepay.com
- **Documentação**: https://docs.abacatepay.com
- **Dashboard**: https://dashboard.abacatepay.com

## Licença

Este plugin é licenciado sob a GPL v3 ou superior. Veja o arquivo LICENSE para mais detalhes.

## Changelog

### Versão 1.0.0
- Lançamento inicial
- Suporte a PIX e Cartão
- Integração com Webhooks
- Validação HMAC-SHA256
- Painel de configurações completo

## Autor

Desenvolvido por **Manus AI** para a AbacatePay.

---

**Nota**: Este plugin é um exemplo de implementação. Para usar em produção, recomenda-se revisar o código e adaptá-lo às suas necessidades específicas.
