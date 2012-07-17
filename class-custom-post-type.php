<?php
class Custom_Post_Type {
	
	private $slug 		= null; 	// Set $slug slug
	private $args 		= array();  // Set $args of custom post type
	private $label 		= array();  // Set $labels of custom post type
	private $roles		= null;		// Set $roles
		
	
	function __construct( $post_type_slug, $args = array(), $labels = array(), $roles = array() ) {
		
		  // Set the slug of the CPT
		  $this->set_slug( $post_type_slug );
		  
		  // Set labels of the CPT
		  $this->set_labels( $post_type_slug, (array)$labels );
		  
		  // Set args of the CPT
		  $this->set_args( $args );
		  
		  // Set roles are allowed to acces to the CPT
		  $this->set_roles( $roles ); 
		  
		  // Call the register_post_type function
		  $this->register_post_type();
		  
		 
		  // Delete the_capacities
		  $this->delete_capacities();
		  
		  if( $this->roles ) {
			    // Set the_capacities
			   $this->set_capacities();
		  }
	}

	
	/*
	 * function set_slug
	 *
	 * Set the slug of the custom post type
	 *
	 * @param string $name
	 *
	*/
	
	function set_slug( $slug ) 
	{
		$this->slug = sanitize_key( $slug );
	}
	
	
	/*
	 * function set_labels
	 *
	 * Set labels of the custom post type
	 *
	 * @param string name
	 * @param array $labels
	 *
	*/
	
	function set_labels( $slug, $labels = array() ) 
	{
		  
		  //Capitilize the words and make it plural  
    	  $singular   = ucwords( preg_replace( '#([_-])#', ' ', $slug ) );  
    	  $plural     = $singular . 's';
		  
		  // Default
	      $this->labels = array_merge(
		      array(  
		           'name'                  => $plural,  
		           'singular_name'         => singular,  
		           'menu_name'             => $plural  
		       )
		       ,
		       $labels
		  );
	}
	
	
	
	/*
	 * function set_args
	 *
	 * Set args of the custom post type
	 *
	 * @param array $args
	 *
	*/
	
	function set_args( $args ) 
	{
		
		$args = is_array( $args ) ? $args : (array)$args;
		$this->args = array_merge(
			  array(
			    'labels' 		=> $this->labels,
			    'public' 		=> true,
			    'has_archive' 	=> true, 
			    'supports' 		=> array('title','editor')
			  )
			  ,
			  $args
		  );
	}
	
	
	/*
	 * function set_roles
	 *
	 * Set roles are allowed to acces of the custom post type
	 *
	 * @param array $roles
	 *
	*/
	
	function set_roles( $roles ) 
	{
		if( !empty( $roles ) ) {
			$roles = is_array( $roles ) ? $roles : (array)$roles;
			$this->roles = $roles;	
		}
		else {
			$this->roles = false;
		}
	}
	
	
	/*
	 * function set_capacities
	 *
	 * TO DO - DESCRIPTION
	 *
	 * @param array $roles
	 *
	*/
	
	private function set_capacities() {
		
		$slug = $this->slug;
		$caps_to_add = array();
		
  		foreach( $this->roles as $role ) {
  			
  			$caps_to_add[] = 'read_' . $slug;
			
			if( $role == 'administrator' || $role == 'editor' || $role == 'author' || $role == 'contributor' ) {
				array_push($caps_to_add, 'edit_' . $slug, 
										 'edit_' . $slug . 's', 
										 'delete_' . $slug, 
										 'delete_' . $slug . 's');
			}
			
				
			if( $role == 'administrator' || $role == 'editor' || $role == 'author' ) {
				array_push($caps_to_add, 'edit_others_' . $slug . 's', 
										 'publish_' . $slug . 's');

				
			}
			
			if( $role == 'administrator' || $role == 'editor' ) {
				array_push($caps_to_add, 'delete_others_' . $slug . 's', 
										 'read_private_' . $slug . 's');
			}
			
			// Get informations of the role
			$r = get_role( $role );
			
			// Add cap for the good roles
			foreach( $caps_to_add as $cap )
			    $r->add_cap( $cap );
			  
			
			$caps_to_add = array(); 
  		}
  		
  		add_filter('map_meta_cap', 
  				   function ( $caps, $cap, $user_id, $args ) use ( $slug ) {
	  				   
	  				    /* If editing, deleting, or reading a client, get the post and post type object. */
						if ( 'edit_' . $slug == $cap || 'delete_' . $slug == $cap || 'read_' . $slug == $cap ) {
							$post = get_post( $args[0] );
							$post_type = get_post_type_object( $post->post_type );
					
							/* Set an empty array for the caps. */
							$caps = array();
							
						}
					
						switch( $cap ) {
			
							/* If editing a post, assign the required capability. */
							case 'edit_' . $slug :
								$caps[] = ($user_id == $post->post_author) ? $post_type->cap->edit_posts : $post_type->cap->edit_others_posts;
								break;
							
							/* If deleting a post, assign the required capability. */
							case 'delete_' . $slug :
								$caps[] = ($user_id == $post->post_author) ? $post_type->cap->delete_posts : $post_type->cap->delete_others_posts;
								break;
							
							case 'read_' . $slug :
								$caps[] = ( 'private' != $post->post_status || $user_id == $post->post_author ) ? 'read' : $post_type->cap->read_private_posts;
								break;
							
				
						}
					
						/* Return the capabilities required by the user. */
						return $caps;
	  				   
  				   } 
  				   ,10 
  				   ,4 );
  		
	}
	
	
	/*
	 * function delete_capacities
	 *
	 * TO DO - DESCRIPTION
	 *
	 * @param array $roles
	 *
	*/
	
	private function delete_capacities() { 
		
		// Get slug
		$slug = $this->slug;
		
		// Get all roles
		$r = new WP_Roles();
  		
  		// TO DO - DESCRIPTION
  		$all_roles = array_diff( array_keys($r->roles), (array)$this->roles);
  		
  		
  		// Get all capacities to remove
  		$caps_to_delete = array('read_' . $slug,
  								'edit_' . $slug, 
								'edit_' . $slug . 's', 
								'delete_' . $slug, 
								'delete_' . $slug . 's',
								'edit_others_' . $slug . 's', 
								'publish_' . $slug . 's',
								'delete_others_' . $slug . 's', 
								'read_private_' . $slug . 's'
  						);
  		
  		
  		// Delete all caps for the others roles
  		foreach ( $all_roles as $role ) {
			
			$r = get_role( $role );
			
			// Add cap for the good roles
			foreach( $caps_to_delete as $cap )
			    $r->remove_cap( $cap );
		}
	}
	
	
	/*
	 * function register_post_type
	 *
	 * Declare and configure a new post type
	 *
	*/
	
	private function register_post_type()
	{
		  $slug 	= $this->slug;
		  $roles 	= $this->roles;
		  $args 	= $this->args;
		  
		  if( !post_type_exists( $slug ) ) {
			  add_action('init', function() use( $slug, $args, $roles ) {
				
					if( $roles ) {
			  			
				  		$args['capability_type'] = $slug;
				  		$args['capabilities'] = array(
				  				'publish_posts' => 'publish_' . $slug. 's',
								'edit_posts' => 'edit_' . $slug . 's',
								'edit_others_posts' => 'edit_others_' . $slug . 's',
								'delete_posts' => 'delete_' . $slug . 's',
								'delete_others_posts' => 'delete_others_' . $slug . 's',
								'read_private_posts' => 'read_private_' . $slug . 's',
								'edit_post' => 'edit_' . $slug,
								'delete_post' => 'delete_' . $slug,
								'read_post' => 'read_' . $slug,
								'edit_page' => 'edit_' . $slug,
				  		);
				  	}
				  	
				  	// Call the register_post_type function
				 	register_post_type( $slug, $args );
					
			  });
		  }
	}

	
	/*
	 * function add_taxonomy
	 *
	 * Declare and configure a new taxonomy for a CPT
	 *
	 * @param string $slug
	 * @param array $labels
	 * @param array $options
	 *
	*/
	
	function add_taxonomy($taxonomy_slug, $post_type, $labels = array(), $args = array() ) {
		
		 //Capitilize the words and make it plural  
    	 $singular   = ucwords( str_replace( '_', ' ', $taxonomy_slug ) );  
    	 $plural     = $singular . 's';
		  
		 // Default
	     $labels = array_merge(
		      array(  
		           'name'                  => $plural,  
		           'singular_name'         => $singular,  
		           'menu_name'             => $plural  
		       )
		       ,
		       $labels
		);
		
		// Args
		$args = is_array( $args ) ? $args : array( $args );
		$args = array_merge(
			  array(
			    'hierarchical' => true,
				'labels' => $labels
			  )
			  ,
			  $args
		);
		
		if( !taxonomy_exists( $taxonomy_slug ) ) {
			add_action('init',
				   function () use( $taxonomy_slug, $post_type, $args ) {
					   register_taxonomy( $taxonomy_slug, $post_type, $args );
				   }
			);				
		}
		else {
			add_action( 'init',  
			    function() use( $taxonomy_slug, $post_type )  
			    {  
			        register_taxonomy_for_object_type( $taxonomy_slug, $post_type );  
			    }  
			);
		}	
	}	
}