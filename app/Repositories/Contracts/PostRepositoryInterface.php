<?php

namespace App\Repositories\Contracts;

interface PostRepositoryInterface
{
    public function createPost(array $data): mixed;
    public function getPost(int $id): mixed;
    public function getAllPosts(array $params): mixed;
    public function getPostsByUserId(array $params): mixed;
    public function updatePost(int $id, array $data): mixed;
    public function deletePost(int $id): mixed;
}
