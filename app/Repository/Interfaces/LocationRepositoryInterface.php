<?php

namespace App\Repository\Interfaces;

interface LocationRepositoryInterface
{
    public function all();
    public function paginate($perPage = 15);
    public function find($id);
    public function create(array $data);
    public function update($id, array $data);
    public function delete($id);
    public function findByName($name);
    public function searchByKeyword($keyword);
    
    // Hierarchical methods
    public function getCities();
    public function getDistricts($cityId = null);
    public function getWards($districtId = null);
    public function getByType($type);
    public function getChildren($parentId);
    public function getTreeStructure();
}