<?php

namespace Gallery;

require_once __DIR__.'/../DatabaseService/DatabaseService.php';
require_once __DIR__.'/../ImageHelper.php';

use Silex\Application;
use Silex\ControllerProviderInterface;
use Silex\ControllerCollection;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

use Gallery\DatabaseService\DatabaseService;
use Gallery\ImageHelper;

class GalleryControllerProvider implements ControllerProviderInterface {
 
 public function connect(Application $app)
 {
//   require_once __DIR__ . '/../../config/config.php';
   
   $app->register(new \Igorw\Silex\ConfigServiceProvider(__DIR__."/../../config/config.yml"));  
   
   $app['database'] = $app->share(function () use($app)   {
       $service = new DatabaseService($app);
       $service->connect();
       return $service;
   }); 
   
   $mustBeLogged = function (Request $request) use ($app) { 
     if (!$app['session']->get('user')) {
       return $app->redirect($app['base_url'] . 'backend/login');
     }
   };  
   
   // adds twig path
   if (is_array($app['twig.path']))
   {
     $app['twig.path'][] = __DIR__ . '/../../views/';
   }
   else
   {
     $app['twig.path'] = array($app['twig.path'], __DIR__ . '/../../views/');
   }
   
   // backend
   $controllers = $app['controllers_factory']; 

   $controllers->match('/backend/login', function (Application $app, Request $request) {
     
     if ($app['session']->get('user'))
     {
       return new RedirectResponse($app['base_url'] . 'backend');
     }
     
     if ('POST' == $request->getMethod()) {
      foreach($app['gallery.backend.accounts'] as $account)
      {
        if (
          ($account['login'] == $request->get('login'))
          &&
          ($account['password'] == $request->get('password'))
        )
        {
          $app['session']->set('user', 1);
          return new RedirectResponse($app['base_url'] . 'backend');          
        }
      }
      return $app['twig']->render('backend/login.twig', array('badCredentials' => true));      
     }
     return $app['twig']->render('backend/login.twig');
   });
   
   $controllers->get('/backend/logout', function (Application $app) {
     $app['session']->set('user', null);
     return new RedirectResponse($app['base_url'] . 'backend');
   })->before($mustBeLogged);
   
   $controllers->get('/backend', function (Application $app) {
     return $app['twig']->render('backend/index.twig');
   })->before($mustBeLogged);
   
   $controllers->get('/backend/{objectType}', function (Application $app, $objectType) {
     if (!in_array($objectType, array_keys($app['gallery.objects'])))
     {
       return new RedirectResponse($app['base_url'] . 'backend');       
     }
     return $app['twig']->render('backend/objects.twig', array(
      'objects' => $app['database']->getObjects($objectType, 'all')
     ));
   })->before($mustBeLogged);
   
   $controllers->match('/backend/{objectType}/{id}', function (Application $app, Request $request, $objectType, $id) {
     if (!in_array($objectType, array_keys($app['gallery.objects'])))
     {
       return new RedirectResponse($app['base_url'] . 'backend');       
     }

     if ('POST' == $request->getMethod()) {
        if($request->get('delete'))
        {
          $app['database']->deleteObject($objectType, $id);
          return new RedirectResponse($app['base_url'] . 'backend/' . $objectType);
        }
        elseif($mediumId = $request->get('delete-media'))
        {
          $app['database']->deleteMedia($objectType, $mediumId);
					$object = $app['database']->getObject($objectType, $id);
        }
        elseif($mediumId = $request->get('delete-doc'))
        {
          $app['database']->deleteDoc($objectType, $mediumId);
					$object = $app['database']->getObject($objectType, $id);
        }        
        else
        {
         $object = $app['database']->saveObject($objectType, $request->request->all(), $id);
         if (!$id || ($id == 'new'))
         {
           return new RedirectResponse($app['base_url'] . 'backend/' . $objectType . '/' . $object['id']);
         }
       }
     }
     elseif(is_numeric($id))
     {
       $fieldList = array();
       foreach ($app['gallery.objects'][$objectType]['fields'] as $fieldName => $field) {
         if (isset($field['multilingual']) && $field['multilingual'])
         {
           foreach(array_keys($app['gallery.languages']) as $lang)
           {
             $fieldList[] = $fieldName . '_' . $lang;
           }
         }
         else
         {
            $fieldList[] = $fieldName;
         }
       }
       $object = $app['database']->getObject($objectType, $id);
     }
     else
     {
       $object = null;
     }
     
     return $app['twig']->render('backend/object.twig', array(
      'object'       => $object,
      'fields'        => $app['database']->fieldsToFieldList($app['gallery.objects'][$objectType]['fields'], $app['gallery.languages']),
      'potentialTags' => $app['database']->getPotentialTags($objectType),
      'potentialCrossingValues' => $app['database']->getPotentialCrossingValues($objectType)        
     ));
   })
   ->assert('id', '[0-9]*|new')
   ->before($mustBeLogged);

   $controllers->match('/backend/{objectType}/{id}/add-media/{mediumType}', function (Application $app, Request $request, $objectType, $mediumType, $id) {
     return $app->redirect('/backend/' . $objectType . '/' . $id . '/add-media/' . $mediumType  . '/new');
   })
   ->before($mustBeLogged);
   
   $controllers->match('/backend/{objectType}/{id}/add-media/{mediumType}/{mediumId}', function (Application $app, Request $request, $objectType, $id, $mediumType, $mediumId) {
		 
		 $isImage = in_array($mediumType, array_keys($app['gallery.objects'][$objectType]['media']['image.formats']));
		 
		 $dir = ($isImage ? 'images' : 'sounds');
		 $type = ($isImage ? 'image' : 'sound');

     if ('POST' == $request->getMethod()) {
       if ($mediumId == 'new') {
         $file = $request->files->get('media');
         $tmpDir = __DIR__ . '/../../../../web/uploads/'.$dir.'/tmp/';
         $destDir = __DIR__ . '/../../../../web/uploads/'.$dir.'/';
				 if ($isImage) {
	         $destinationFilename = $objectType . '_image_' . $id . '_' . rand(1, 100000) . '.jpg';				 	
				 } else {
				 	 $destinationFilename = $objectType . '_sound_' . $id . '_' . rand(1, 100000) . '.mp3';				 	
				 }
				 
				 if ($isImage) {
	         if (isset($app['gallery.objects'][$objectType]['media']['image.formats'][$mediumType])) {
	           $fileFormat = $app['gallery.objects'][$objectType]['media']['image.formats'][$mediumType];           
	         } else {
	           $fileFormat = $app['gallery.pages'][$objectType]['media']['image.formats'][$mediumType];           
	         }				 	
				 }

				 if ($isImage) {			 
	         $file->move($tmpDir, $file->getClientOriginalName());
	         ImageHelper::resize($destDir, $tmpDir, $destinationFilename, $file->getClientOriginalName(), $fileFormat['maxWidth'], $fileFormat['maxHeight']);
	         if (isset($fileFormat['previewMaxWidth'])) {
	           $destDir = __DIR__ . '/../../../../web/uploads/image-previews/';         
	           ImageHelper::resize($destDir, $tmpDir, $destinationFilename, $file->getClientOriginalName(), $fileFormat['previewMaxWidth'], $fileFormat['previewMaxHeight']);         
	         }
				 } else {
					 $destDir = __DIR__ . '/../../../../web/uploads/sounds/';
	         $file->move($destDir, $destinationFilename);
				 }
       }
       $app['database']->saveMedium($objectType, isset($destinationFilename) ? $destinationFilename : null, $mediumType, $id, $request->request->all(), $mediumId);
       
       // page names
       if (in_array($objectType, array_keys($app['gallery.pages']))) {
         return new RedirectResponse($app['base_url'] . 'backend/page/' . $objectType);
       }       
       
       return new RedirectResponse($app['base_url'] . 'backend/' . $objectType . '/' . $id);
     }

     if (!$mediumId || ($mediumId == 'new')) {
       $medium = null;         
     } else {
       $medium = $app['database']->getMedium($objectType, $mediumType, $mediumId);
     }
     
     if (isset($app['gallery.objects'][$objectType]['media'][$type.'.formats'][$mediumType]['fields'])) {
       $fields = $app['gallery.objects'][$objectType]['media'][$type.'.formats'][$mediumType]['fields'];
     } elseif (isset($app['gallery.pages'][$objectType]['media'][$type.'.formats'][$mediumType]['fields'])) {
       $fields = $app['gallery.pages'][$objectType]['media'][$type.'.formats'][$mediumType]['fields'];
     } else {
       $fields = array();
     }
		 
     return $app['twig']->render('backend/add-media.twig', array(
       'medium' => $medium,
       'fields' => $app['database']->fieldsToFieldList($fields, $app['gallery.languages'])
     ));
   })
   ->before($mustBeLogged);   
   
   $controllers->match('/backend/{objectType}/{id}/add-doc/{docType}', function (Application $app, Request $request, $objectType, $docType, $id) {
     return $app->redirect(substr($app['base_url'], 0, -1).'/backend/' . $objectType . '/' . $id . '/add-doc/' . $docType  . '/new');
   })
   ->before($mustBeLogged);
      
   $controllers->match('/backend/{objectType}/{id}/add-doc/{docType}/{docId}', function (Application $app, Request $request, $objectType, $id, $docType, $docId) {

     if ('POST' == $request->getMethod()) {
       if ($docId == 'new') {
         $file = $request->files->get('doc');
         $destDir = __DIR__ . '/../../../../web/uploads/docs/';
         $destinationFilename = $file->getClientOriginalName();
       
         $file->move($destDir, $file->getClientOriginalName());
         
         
          $previewFile = $request->files->get('preview');
          if ($previewFile) {
          
            $tmpDir = __DIR__ . '/../../../../web/uploads/doc-previews/tmp/';
            $destDir = __DIR__ . '/../../../../web/uploads/doc-previews/';
          
            $previewDestinationFilename = substr($file->getClientOriginalName(), 0, strrpos($file->getClientOriginalName()  , '.')) . '_preview.jpg';
            $previewFile->move($tmpDir, $previewFile->getClientOriginalName());
            
            if (isset($app['gallery.objects'][$objectType]['doc']['formats'][$docType])) {
              $fileFormat = $app['gallery.objects'][$objectType]['doc']['formats'][$docType];            
            } else {
              $fileFormat = $app['gallery.pages'][$objectType]['doc']['formats'][$docType];
            }
            ImageHelper::resize($destDir, $tmpDir, $previewDestinationFilename, $previewFile->getClientOriginalName(), $fileFormat['previewMaxWidth'], $fileFormat['previewMaxHeight']);
          }
        }
      
       $app['database']->saveDoc($objectType, $request->get('title'), isset($destinationFilename) ? $destinationFilename  : null, isset($previewDestinationFilename) ? $previewDestinationFilename : null, $docType, $id, $request->request->all(), $docId);
       
       // page names
       if (in_array($objectType, array_keys($app['gallery.pages']))) {
         return new RedirectResponse($app['base_url'] . 'backend/page/' . $objectType);
       }
       // else 
       return new RedirectResponse($app['base_url'] . 'backend/' . $objectType . '/' . $id);         
       
     }
     
     if (!$docId || ($docId == 'new')) {
       $doc = null;         
     } else {
       $doc = $app['database']->getDoc($objectType, $docType, $docId);
     }
     
     if (isset($app['gallery.objects'][$objectType]['doc']['formats'][$docType]['fields'])) {
       $fields = $app['gallery.objects'][$objectType]['doc']['formats'][$docType]['fields'];
     } elseif (isset($app['gallery.pages'][$objectType]['doc']['formats'][$docType]['fields'])) {
       $fields = $app['gallery.pages'][$objectType]['doc']['formats'][$docType]['fields'];
     } else {
       $fields = array();
     }
		 
		 if (isset($app['gallery.objects'][$objectType]['doc']['formats'][$docType]['previewMaxHeight'])) {
			 $previewMaxHeight = $app['gallery.objects'][$objectType]['doc']['formats'][$docType]['previewMaxHeight'];
		 } else {
			 $previewMaxHeight = null;
		 }

		 if (isset($app['gallery.objects'][$objectType]['doc']['formats'][$docType]['previewMaxWidth'])) {
			 $previewMaxWidth = $app['gallery.objects'][$objectType]['doc']['formats'][$docType]['previewMaxWidth'];
		 } else {
			 $previewMaxWidth = null;
		 }
          
     return $app['twig']->render('backend/add-doc.twig', array(
       'doc' => $doc,
			 'previewMaxWidth'  => $previewMaxWidth,
			 'previewMaxHeight' => $previewMaxHeight,
       'fields' => $app['database']->fieldsToFieldList($fields, $app['gallery.languages'])
     ));     
   })
   ->before($mustBeLogged);   
   
 $controllers->match('/backend/page/{pageName}', function (Application $app, Request $request, $pageName) {
   
   if (!isset($app['gallery.pages'][$pageName]))
   {
       return new RedirectResponse($app['base_url'] . 'backend');
   }
   
   if ('POST' == $request->getMethod()) {
     if($mediumId = $request->get('delete-media'))
     {
       $app['database']->deleteMedia($pageName, $mediumId);
       $page = $app['database']->getPage($pageName);
     }
     elseif($mediumId = $request->get('delete-doc'))
     {
       $app['database']->deleteDoc($pageName, $mediumId);
       $page = $app['database']->getPage($pageName);
     } else {
       $page = $app['database']->savePage($request->request->all(), $pageName);       
     }

     if (!$page)
     {
       return new RedirectResponse($app['base_url'] . 'backend/' . $pageName);
     }
   }
   else
   {
     $fieldList = array();
     if (is_array($app['gallery.pages'][$pageName]['fields'])) {
       foreach ($app['gallery.pages'][$pageName]['fields'] as $fieldName => $field) {
         if (isset($field['multilingual']) && $field['multilingual'])
         {
           foreach(array_keys($app['gallery.languages']) as $lang)
           {
             $fieldList[] = $fieldName . '_' . $lang;
           }
         }
         else
         {
            $fieldList[] = $fieldName;
         }
       }       
     }
     $page = $app['database']->getPage($pageName);
   }
   
   return $app['twig']->render('backend/page.twig', array(
    'page'    => $page,
    'fields'  => $app['database']->fieldsToFieldList($app['gallery.pages'][$pageName]['fields'], $app['gallery.languages'])
   ));
 })
 ->before($mustBeLogged);
   
   
 $controllers->post('/backend/{objectType}/reorder', function (Application $app, Request $request, $objectType) {
   $objectIds = explode(';', $request->get('objectOrder'));
   $app['database']->reorderProjects($objectType, array_combine($objectIds, range(1, count($objectIds))));
   return new Response('1');
 })
 ->before($mustBeLogged);
 
 $controllers->post('/backend/{objectType}/{mediaType}/reorder', function (Application $app, Request $request, $objectType, $mediaType) {
   $objectIds = explode(';', $request->get('objectOrder'));
   
   if (
     isset($app['gallery.objects'][$objectType]['media']['image.formats'][$mediaType]) 
    ||
      isset($app['gallery.pages'][$objectType]['media']['image.formats'][$mediaType]) 
     ) {
       $app['database']->reorderMedia($objectType, $mediaType, array_combine($objectIds, range(1, count($objectIds))));
     } else {
       $app['database']->reorderDocs($objectType, $mediaType, array_combine($objectIds, range(1, count($objectIds))));
     }
   
   return new Response('1');
 })
 ->before($mustBeLogged);   
 

  $controllers->get('/backend/{objectType}/tags', function (Application $app, Request $request, $objectType) {
    return $app['twig']->render('backend/categories.twig');  
  })
  ->before($mustBeLogged);   

  $controllers->match('/backend/{objectType}/tags/{category}', function (Application $app, Request $request, $objectType, $category) {
    if ('POST' == $request->getMethod()) {
      if ($tagId = $request->get('delete'))
      {
        $app['database']->deleteTag($objectType, $tagId);
      }
      else{
        $app['database']->addTag($objectType, $request->get('tag'), $category);        
      }
    }    
    
    $tags = $app['database']->getCategoryTags($objectType, $category);
    return $app['twig']->render('backend/category.twig', array(
      'category' => $category,
      'tags'     => $tags
    ));  
  })
  ->before($mustBeLogged);   
   
   return $controllers; 
   
 }
 
}