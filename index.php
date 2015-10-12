<?php
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

require_once __DIR__.'/vendor/autoload.php'; 

$app = new Silex\Application();
$app['debug'] = true;

$app->register(new JDesrosiers\Silex\Provider\CorsServiceProvider(), array(
    "cors.allowOrigin" => "*",
));
$app->after($app["cors"]);

require_once __DIR__ . 'config.php';

$app->get('/client_token/{customerId}', function($customerId) use($app) { 

	$clientToken = Braintree_ClientToken::generate([
	    "customerId" => $customerId
	]);

	return $clientToken;
})->value('customerId', null); 

$app->post('/customer', function(Request $request) use($app) {
	$data = json_decode($request->getContent(), true);

	$result = Braintree_Customer::create($data['customer']);

	if ($result->success) {
		return $result->customer->id;
	}

	return new Response('Could not create customer', 400);
});

$app->post('/payment_method', function(Request $request) use($app) {
	$data = json_decode($request->getContent(), true);

	$result = Braintree_PaymentMethod::create([
		'customerId' => $data['paymentMethod']['customerId'],
	    'paymentMethodNonce' => $data['paymentMethod']['paymentMethodNonce'],
	    'options' => [
	      'makeDefault' => true,
	      'failOnDuplicatePaymentMethod' => true,
	      'verifyCard' => true
	    ]
	]);

	if ($result->success) {
		return new Response($result->paymentMethod->token, 200);
	}

	return new Response('Could not save payment method. Original message: ' . $result->message, 400);
});

$app->post('/sale', function(Request $request) use($app) {
	$data = json_decode($request->getContent(), true);

	$result = Braintree_Transaction::sale([
	  'amount' => $data['transaction']['amount'],
	  'paymentMethodToken' => $data['transaction']['paymentMethodToken'],
	  'options' => [
	    'submitForSettlement' => true
	  ]
	]);

	if ($result->success) {
		return new Response($result->transaction->id, 200);
	}

	return new Response('Transaction has failed. Original message: ' . $result->message, 400);
});

$app->run(); 