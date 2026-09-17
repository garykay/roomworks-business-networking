<?php
// This file is generated. Do not modify it manually.
return array(
	'roomworks-business-networking' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'roomworks-business-networking/roomworks-business-networking',
		'version' => '0.1.0',
		'title' => 'Business Directory',
		'category' => 'widgets',
		'icon' => 'networking',
		'description' => 'Searchable, filterable directory of approved businesses.',
		'example' => array(
			
		),
		'supports' => array(
			'html' => false
		),
		'textdomain' => 'roomworks-business-networking',
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./index.css',
		'style' => 'file:./style-index.css',
		'render' => 'file:./render.php',
		'viewScript' => 'file:./view.js'
	),
	'roomworks-stats-counter' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'create-block/roomworks-stats-counter',
		'version' => '0.1.0',
		'title' => 'Roomworks Stats Counter',
		'category' => 'widgets',
		'icon' => 'chart-bar',
		'description' => 'Displays live counts of members, listed businesses and UK cities covered.',
		'example' => array(
			
		),
		'supports' => array(
			'html' => false,
			'align' => array(
				'wide',
				'full'
			)
		),
		'attributes' => array(
			'membersLabel' => array(
				'type' => 'string',
				'default' => 'Members'
			),
			'membersSuffix' => array(
				'type' => 'string',
				'default' => ''
			),
			'businessesLabel' => array(
				'type' => 'string',
				'default' => 'Businesses Listed'
			),
			'businessesSuffix' => array(
				'type' => 'string',
				'default' => '+'
			),
			'citiesLabel' => array(
				'type' => 'string',
				'default' => 'UK Cities'
			),
			'citiesSuffix' => array(
				'type' => 'string',
				'default' => '+'
			)
		),
		'textdomain' => 'roomworks-stats-counter',
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./index.css',
		'style' => 'file:./style-index.css',
		'render' => 'file:./render.php'
	),
	'single-business-profile' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'roomworks-business-networking/single-business-profile',
		'version' => '0.1.0',
		'title' => 'Single Business Profile',
		'category' => 'widgets',
		'icon' => 'store',
		'description' => 'Shows business type, services, location, contact details, and owner. Place inside a Single Business template in the Site Editor alongside Post Title/Post Content for the name and description.',
		'example' => array(
			
		),
		'supports' => array(
			'html' => false,
			'multiple' => false
		),
		'usesContext' => array(
			'postId',
			'postType'
		),
		'textdomain' => 'roomworks-business-networking',
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./index.css',
		'style' => 'file:./style-index.css',
		'render' => 'file:./render.php'
	),
	'user-profile' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'roomworks-business-networking/user-profile',
		'version' => '0.1.0',
		'title' => 'Member Profile',
		'category' => 'widgets',
		'icon' => 'businessman',
		'description' => 'Displays a member\'s profile and their business.',
		'example' => array(
			
		),
		'supports' => array(
			'html' => false
		),
		'textdomain' => 'roomworks-business-networking',
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./index.css',
		'style' => 'file:./style-index.css',
		'render' => 'file:./render.php',
		'viewScript' => 'file:./view.js'
	)
);
