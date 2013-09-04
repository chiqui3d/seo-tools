<?php
$app['saved_queries'] = function() use ($app) {
	return $app['twig']->render('select_query.twig', array( 'saved_queries' => $app['orm.em']->getRepository( 'DomainFinder\Entity\Query' )->findAll() ) );
};

$app->get('/new', function() use ($app) {
	$params = array();
	$errors = $app['request']->getSession()->getFlashBag()->get('errors');
	if ( !empty( $errors ) )
	{
		$params['errors'] = $errors;
	}
	
	return $app['twig']->render('new.twig', $params);
})->bind( 'new_query' );

$app->post('/new', function() use ($app) {
	$request  = array(
		'query' 	=> $app['request']->request->get( 'query' ),
		'domains' 	=> $app['request']->request->get( 'domains' )
	);

	try
	{
		$use_case = new DomainFinder\UseCase\SaveQuery( $app['orm.em']->getRepository( 'DomainFinder\Entity\Query' ) );
		$response = $use_case->run( $request );
		return $app->redirect( $app['url_generator']->generate('queries') );
	}
	catch ( \Exception $e)
	{
		$app['request']->getSession()->getFlashBag()->set( 'errors', array( 'The query \'' . $app['request']->request->get( 'query' ) . '\' already exists.' ) );
		return $app->redirect( $app['url_generator']->generate('new_query') );
	}
});

$app->get('/edit/{query}', function($query) use ($app) {
	$errors 	= $app['request']->getSession()->getFlashBag()->get('errors');
	$use_case 	= new DomainFinder\UseCase\ShowQuery( $app['orm.em']->getRepository( 'DomainFinder\Entity\Query' ) );
	$response 	= $use_case->run( array( 'query' => $query ) );
	
	$params 	= array_merge( $response, array( 'errors' => $errors ) );

	return $app['twig']->render('edit.twig', $params );
})->bind('edit_query');

$app->post('/edit', function() use ($app) {
	$request = array(
		'domains' 			=> $app['request']->request->get( 'domains' ),
		'query' 			=> $app['request']->request->get( 'query' ),
		'original_query' 	=> $app['request']->request->get( 'original_query' )
	);

	try
	{
		$use_case 	= new DomainFinder\UseCase\UpdateQuery( $app['orm.em']->getRepository( 'DomainFinder\Entity\Query' ) );
		$response = $use_case->run( $request );
		return $app->redirect( $app['url_generator']->generate('queries') );
	}
	catch ( \Exception $e)
	{
		$app['request']->getSession()->getFlashBag()->set( 'errors', array( 'The query \'' . $app['request']->request->get( 'query' ) . '\' already exists.' ) );
		return $app->redirect( $app['url_generator']->generate( 'edit_query', array( 'query' => $app['request']->request->get( 'original_query' ) ) ) );
	}
})->bind('edit_query_post');

$app->get('/delete/{query}', function($query) use ($app) {
	$use_case 	= new DomainFinder\UseCase\ShowQuery( $app['orm.em']->getRepository( 'DomainFinder\Entity\Query' ) );
	$response 	= $use_case->run( array( 'query' => $query ) );

	return $app['twig']->render('delete.twig', $response );
})->bind('delete_query');

$app->post('/delete', function() use ($app) {
	$use_case 	= new DomainFinder\UseCase\DeleteQuery( $app['orm.em']->getRepository( 'DomainFinder\Entity\Query' ) );
	$response 	= $use_case->run( array( 'query' => $app['request']->request->get( 'query' ) ) );
	
	return $app->redirect( $app['url_generator']->generate('queries') );
})->bind('delete_query_post');

$app->get('/', function() use ($app) {
	$use_case = new DomainFinder\UseCase\QueryList( $app['orm.em']->getRepository( 'DomainFinder\Entity\Query' ) );
	$response = $use_case->run();

	return $app['twig']->render( 'queries.twig', $response );
})->bind( 'queries' );

$app->get('/chart', function() use ($app) {
	$path 		= $app['request']->getBasepath();
	$query 		= $app['request']->query->get( 'q' );
    $domains 	= array();
	$logs 		= $app['orm.em']->getRepository( 'DomainFinder\Entity\Rank' )->findBy( array( 'query' => $query ), array( 'date' => 'asc', 'domain' => 'asc' ) );
	foreach ( $logs as $log ) {
	  $domains[] = $log->getDomain();
	  $points[$log->getDate()][] = array(
	    'domain'    => $log->getDomain(),
	    'position'  => $log->getPosition()
	  );
	}
	if ( empty( $points ) )
	{
		return $app['twig']->render('no_chart.twig');
	}

	$domains  = array_unique( $domains );
	$domains  = array_values ( $domains );
	$rows     = array();
	$rows[]   = array_merge( array( 'date' ), $domains );
	foreach ( $points as $date => $logs ) {
	  $row = array( date( 'd/m/Y', strtotime( $date ) ) );
	  foreach ( $logs as $log ) {
	    $column_for_chart = array_search( $log['domain'], $domains );
	    $row[$column_for_chart+1] = (int)$log['position'];
	  }

	  for($i = 0; $i < count($row); $i++)
	  {
	      if(!isset($row[$i]))
	      {
	          $row[$i] = null;
	      }
	  }

	  $keys = array_keys($row);
	  natsort($keys);

	  foreach ($keys as $k)
	    $row2[$k] = $row[$k];

	  if ( ( count( $row2 ) - 1 ) < count( $domains ) ){
	    $row2 = array_merge( $row2, array( null ) );
	  }
	  $rows[] = $row2;
	}

    return $app['twig']->render('chart.twig', array(
        'query' => $query,
        'chart' => json_encode( $rows )
    ));
})->bind('chart');

$app->error(function (\Exception $e, $code) use ($app) {
    if ( $app['debug'] ) {
        return;
    }


    $page = 404 == $code ? '404.twig' : '500.twig';

    return new \Symfony\Component\HttpFoundation\Response($app['twig']->render($page, array('code' => $code)), $code);
});