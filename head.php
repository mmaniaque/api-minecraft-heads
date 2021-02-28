<?php

// Configuration 
$cacheTime = "+24 hours"; // une heure de cache par joueur

// ! NE PAS TOUCHER AU CODE EN DESSOUS !

// Show all PHP erros
ini_set('display_startup_errors',1);
ini_set('display_errors',1);
error_reporting(-1);

// Importation de Redis
require "vendor/autoload.php";

// Chargement de la library Redis
Predis\Autoloader::register();

// Connexion à Redis
try
{
	
	$redis = new Predis\Client([
		"scheme" => "tcp",
		"host" => "127.0.0.1",
		"port" => 6379,
		"password" => ""
	]);
	
}
catch (Exception $e)
{
	die($e->getMessage());
}

// Paramètres utilisateur : taille de l'image, utilisateur, vue du skin etc..
$size = isset($_GET['s']) ? max(8, min(512, $_GET['s'])) : 48;
$user = isset($_GET['u']) ? $_GET['u'] : '';
$view = isset($_GET['v']) ? substr($_GET['v'], 0, 1) : 'f';
$view = in_array($view, array('f', 'l', 'r', 'b')) ? $view : 'f';

// Récupération d'une tête
function get_head($redis, $cacheTime, $user, $size, $view)
{
	// On met en lower le nom d'utilisateur
	$user = strtolower($user);
	
	// Clé redis
	$key = $user."_".$size."_".$view;
	
	// Utilisateur vide ou inconnu
    if ($user == null OR empty($user))
	{
		// On lui met donc le skin par défaut
		return get_default_head($redis, "steve", $size, $view, "+1 minute");
    }
	
	try
	{
		
		// On récupère le skin actuel de l'utilisateur dans Redis
		$value = $redis->get($key);
		
		// La valeur du skin n'existe pas dans Redis, on va la créer
		// Elle a soit expirée soit c'est la première fois que le cache se créée
		// pour ce joueur
		if ($value == null OR empty($value))
		{
			// On récupère le temps actuel
			$time = time();
			
			// On fait le lien du profil du joueur avec son pseudo pour avoir l'UUID du joueur
			$url = "https://api.mojang.com/users/profiles/minecraft/$user?at=$time";
			// On récupère les données avec ce lien-là
			$uuid = @file_get_contents($url);
			// Si le profil est inconnu
			if ($uuid == null)
			{
				return get_default_head($redis, $user, $size, $view, $cacheTime);
			}
			// On décode le résultat JSON donné par l'API de Mojang
			$uuid = json_decode($uuid);
			// On récupère le champ "id" qui contient l'UUID dans la réponse
			$uuid = $uuid->id;
			
			// On récupère le profile grâce à l'UUID qu'on a précédemment récupéré
			$profile = @file_get_contents("https://sessionserver.mojang.com/session/minecraft/profile/$uuid");
			// Si le profil est inconnu
			if ($profile == null)
			{
				return get_default_head($redis, $user, $size, $view, "+1 minute");
			}
			// On décode le résultat JSON donné par l'API de Mojang
			$profile = json_decode($profile);
			// On récupère les propriétés du profil
			$properties = $profile->properties[0];
			// On récupère la valeur des textures du profil Mojang
			$properties = $properties->value;
			
			// On décode la valeur en base64 des textures
			$textures = base64_decode($properties);
			// On décode le résultat JSON pour le transformer en objet
			$textures = json_decode($textures);
			// On récupère la liste des textures du profil du compte
			$textures = $textures->textures;
			// Si texture inconnue, on le redirige vers la tête par défaut
			if ($textures == null)
			{
				return get_default_head($redis, $user, $size, $view, "+1 minute");
			}
			// Si aucun skin existe avec la texture
			if (!property_exists($textures, "SKIN"))
			{
				return get_default_head($redis, $user, $size, $view, $cacheTime);
			}
			// On récupère le SKIN précisément, et non la cape
			$skin = $textures->SKIN;
			// On récupère l'URL du skin téléversé sur les serveurs de Mojang
			$skinUrl = $skin->url;
			
			// On récupère le contenu du fichier du skin
			$output = file_get_contents($skinUrl);
			
			// Si tout s'est bien passé et que le skin nous est parvenu
			if ($output != null && !empty($output))
			{
				// Dans ce cas on récupère juste la tête pour optimiser les ressources
				$head = getHeadFromSkin($output, $size, $view);
				// On encode la tête en base64
				$encodedHead = base64_encode($head);
				// La sortie sera donc la tête
				$output = $head;
				// Alors dans ce cas si tout va bien on enregistre la tête encodée dans le cache
				// pour une durée configurable
				$redis->set($key, $encodedHead);
				$redis->expireat($key, strtotime($cacheTime));
				// On retourne la tête si tout s'est bien passé
				return $head;
			}
			
			// Pas de tête retournée, donc on retourne la tête de steve
			return get_default_head($redis, $user, $size, $view,"+1 minute");
		}
		else
		{
			return base64_decode($value);
		}
	}
	catch(Exception $e)
	{
		// En cas d'erreur, on met la tête par défaut
		return get_default_head($redis, $user, $size, $view, "+1 minute");
	}
}

// Récupération de la tête par défaut
function get_default_head($redis, $user, $size, $view, $cacheTime)
{
	// To lower :o
	$user = strtolower($user);
	// Clé redis
	$key = $user."_".$size."_".$view;
	// On récupère le contenu du skin de steve
	$steveContent = @file_get_contents('steve.png');
	// On récupère la tête depuis le skin de steve
	$headFromSkin = getHeadFromSkin($steveContent, $size, $view);
	// On encode le contenu de la tête de steve
	$encodedHead = base64_encode($headFromSkin);
	// On cache le joueur avec la tête de steve
	$redis->set($key, $encodedHead);
	$redis->expireat($key, strtotime($cacheTime));
	// On envoie la tête de steve
	return $headFromSkin;
}

// Récupération de la tête depuis le skin
function getHeadFromSkin($skin, $size, $view)
{
	// Création de l'image depuis un string
	$im = imagecreatefromstring($skin);
	// On créé une image colorisée de taille définie
	$av = imagecreatetruecolor($size, $size);
	// On récupère le x de la tête dans le skin
	$x = array('f' => 8, 'l' => 16, 'r' => 0, 'b' => 24);
	// On recadre la tête en fonction du x
	imagecopyresized($av, $im, 0, 0, $x[$view], 8, $size, $size, 8, 8);
	// Transparence
	imagecolortransparent($im, imagecolorat($im, 63, 0));
	// On copie la tête et on la recadre encode
	imagecopyresized($av, $im, 0, 0, $x[$view] + 32, 8, $size, $size, 8, 8);
	// On détruit l'image par défaut
	imagedestroy($im);
	// On buffer pour récupérer l'image png
	ob_start();
	// Récupération de l'image png
	imagepng($av);
	// Récupération du contenu donné de l'image png
	$contents =  ob_get_contents();
	// On clean le buffer & on termine le buffer, on n'en a plus besoin
	ob_end_clean();
	// On retourne le contenu de la tête
	return $contents;
}

// Au final :

// Cache de 24h
header("Cache-Control: max-age=86400");
// Génération de l'expiration de la page
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
// Le type de la page sera une image PNG
header('Content-type: image/png');
// On récupère le code source de la tête avec le processus
// de cache et les paramètres spécifiques
$head = get_head($redis, $cacheTime, $user, $size, $view);
// On affiche la tête
echo $head;

?>
