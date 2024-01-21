<?php

namespace App\Controllers;

use Framework\Authorization;
use Framework\Session;
use Framework\Database;
use Framework\Validation;

class ListingsController
{
    protected $db;
    public function __construct()
    {
        $config = require basePath('config/db.php');

        $this->db = new Database($config);
    }

    /**
     * Show the listings
     *
     * @return void
     */
    public function index()
    {
        $listings = $this->db->query('SELECT * FROM listings ORDER BY created_at DESC')->fetchAll();

        // Fetch Data
        loadView('listings/index', [
            'listings' => $listings
        ]);
    }

    /**
     * Show list create form
     *
     * @return void
     */
    public function create()
    {
        loadView('listings/create');
    }


    /**
     * Show the single list
     *@param array $params
     * @return void
     */
    public function show($params)
    {
        $id = $params['id'] ?? '';
        $params = [
            'id' => $id
        ];
        $listing = $this->db->query('SELECT * FROM listings WHERE id = :id', $params)->fetch();

        //Check if listing exists

        if (!$listing) {
            ErrorController::notFound('Listing not found!');

            return;
        }
        loadView('listings/show', [
            'listing' => $listing
        ]);
    }

    /**
     * Store data to Database
     * 
     * @return void
     */

    public function store()
    {
        $allowedFields = ['title', 'description', 'salary', 'tags', 'company', 'address', 'city', 'state', 'phone', 'email', 'requirements', 'benefits'];

        $newListing = array_intersect_key($_POST, array_flip($allowedFields));

        $newListing['user_id'] = Session::get('user')['id'];

        $newListing = array_map('sanitize', $newListing);

        $requiredFields = ['title', 'description', 'salary', 'email', 'city', 'state'];


        $errors = [];

        foreach ($requiredFields as $field) {
            if (empty($newListing[$field]) || !Validation::string($newListing[$field])) {
                $errors[$field] = ucfirst($field) . ' is required';
            }
        }

        if (!empty($errors)) {
            loadView('listings/create', [
                'errors' => $errors,
                'listing' => $newListing
            ]);
            exit;
        } else {
            // Submit data
            $fields = [];

            foreach ($newListing as $field => $value) {
                $fields[] = $field;
            }

            $fields = implode(', ', $fields);

            $values = [];

            foreach ($newListing as $field => $value) {
                // Convert empty strings to null
                if ($value === '') {
                    $newListing[$field] = null;
                }
                $values[] = ':' . $field;
            }

            $values = implode(', ', $values);

            $query = "INSERT INTO listings ({$fields}) VALUES ({$values})";


            //Success store
            Session::setFlashMessage('success_message', 'Created Successfully');
            $this->db->query($query, $newListing);

            redirect('/listings');
        }
    }


    /**
     * Delete the listing from Database
     * 
     * @param array $params
     * @return void
     */

    public function destroy($params)
    {
        $id = $params['id'];

        $params = [
            'id' => $id
        ];

        $listing = $this->db->query('SELECT * FROM listings WHERE id = :id', $params)->fetch();

        //Check listing exists
        if (!$listing) {
            ErrorController::notFound('Listing not found');
            return;
        }

        //Authorization for deletation
        if (!Authorization::isOwner($listing->user_id)) {

            Session::setFlashMessage('error_message', 'You are not authorized to delete this listing');
            return redirect('/listings/' . $listing->id);
        };

        $this->db->query('DELETE FROM listings WHERE id = :id', $params);

        // Set flash message
        Session::setFlashMessage('success_message', 'Listing deleted successfully');
        redirect('/listings');
    }


    /**
     * Show Listing edit form
     *@param array $params
     * @return void
     */
    public function edit($params)
    {
        $id = $params['id'] ?? '';
        $params = [
            'id' => $id
        ];
        $listing = $this->db->query('SELECT * FROM listings WHERE id = :id', $params)->fetch();

        //Check if listing exists

        if (!$listing) {
            ErrorController::notFound('Listing not found!');

            return;
        }

        //Authorization for edit button
        if (!Authorization::isOwner($listing->user_id)) {

            Session::setFlashMessage('error_message', 'You are not authorized to edit this listing');
            return redirect('/listings/' . $listing->id);
        };

        loadView('listings/edit', [
            'listing' => $listing
        ]);
    }


    /**
     * Update a listing
     * 
     * @param array $params
     * @return void
     */
    public function update($params)
    {
        $id = $params['id'] ?? '';

        $params = [
            'id' => $id
        ];

        $listing = $this->db->query('SELECT * FROM listings WHERE id = :id', $params)->fetch();

        // Check if listing exists
        if (!$listing) {
            ErrorController::notFound('Listing not found');
            return;
        }

        //Authorization for updates
        if (!Authorization::isOwner($listing->user_id)) {

            Session::setFlashMessage('error_message', 'You are not authorized to update this listing');
            return redirect('/listings/' . $listing->id);
        };

        $allowedFields = ['title', 'description', 'salary', 'tags', 'company', 'address', 'city', 'state', 'phone', 'email', 'requirements', 'benefits'];

        $updateValues = [];

        $updateValues = array_intersect_key($_POST, array_flip($allowedFields));

        $updateValues = array_map('sanitize', $updateValues);

        $requiredFields = ['title', 'description', 'salary', 'email', 'city', 'state'];

        $errors = [];

        foreach ($requiredFields as $field) {
            if (empty($updateValues[$field]) || !Validation::string($updateValues[$field])) {
                $errors[$field] = ucfirst($field) . ' is required';
            }
        }

        if (!empty($errors)) {
            loadView('listings/edit', [
                'listing' => $listing,
                'errors' => $errors
            ]);
            exit;
        } else {
            // Submit to database
            $updateFields = [];

            foreach (array_keys($updateValues) as $field) {
                $updateFields[] = "$field = :$field";
            }


            $updateFields = implode(', ', $updateFields);

            $updateQuery = "UPDATE listings SET $updateFields WHERE id = :id";

            $updateValues['id'] = $id;
            $this->db->query($updateQuery, $updateValues);

            // Set flash message
            Session::setFlashMessage('success_message', 'Listing updatedd successfully');
            redirect('/listings/' . $id);
        }
    }

    /**
     * Search listing by keywords/location
     * 
     * @return void
     */

    public function search()
    {
        $keywords = isset($_GET['keywords']) ? trim($_GET['keywords']) : '';
        $location = isset($_GET['location']) ? trim($_GET['location']) : '';


        $query = "SELECT * FROM listings WHERE (title LIKE :keywords OR description LIKE :keywords OR tags LIKE :keywords OR company LIKE :keywords) AND (city LIKE :location OR state LIKE :location)";

        $params = [
            'keywords' => "%{$keywords}%",
            'location' => "%{$location}%"
        ];

        $listings = $this->db->query($query, $params)->fetchAll();

        loadView('/listings/index', [
            'listings' => $listings,
            'keywords' => $keywords,
            'location' => $location
        ]);
    }
}
