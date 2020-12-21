<?php

declare(strict_types=1);

namespace App\Controller;

use Cake\Datasource\Exception\RecordNotFoundException;

class UsersController extends AppController
{
    public function index()
    {
        $page = $this->request->getQuery('page', 1);
        $limit = $this->request->getQuery('limit', 10);
        $order = $this->request->getQuery('order', 'id');
        $sort = $this->request->getQuery('sort', 'asc');
        $keyword = $this->request->getQuery('keyword');

        $cacheKey = "users::list::" . serialize([
            "page" => $page,
            "limit" => $limit,
            "sort" => $sort,
            "keyword" => $keyword
        ]);

        $response = $this->redis->get($cacheKey);

        if (!$response) {
            $page = intval($page);
            $limit = intval($limit);

            if (!in_array($order, ['id', 'name'])) {
                $order = "id";
            }
            if ($page < 1) {
                $page = 1;
            }
            if ($limit < 1) {
                $limit = 1;
            }
            $sort = strtolower($sort);
            if (!in_array($sort, ['asc', 'desc'])) {
                $sort = 'asc';
            }

            $users = $this->Users->find()->order([$order => $sort]);
            if ($keyword) {
                $users = $users->where(['name LIKE ' => "%$keyword%"]);
            }
            $count = $users->count();
            if ($limit > $count) {
                $limit = $count;
            }
            if (($page * $limit) > $count) {
                $page = intval(ceil($count / $limit));
            }
            $users = $users->limit($limit)->page($page)->all();

            if (empty($users->toArray())) {
                throw new RecordNotFoundException(__('User Not Found'));
            }

            $pagination = [
                'page' => $page,
                'limit' => $limit,
                'order' => $order,
                'sort' => $sort,
                'count' => $count,
                'keyword' => $keyword
            ];

            $response = [
                "status_code" => "cdc-200",
                "status_message" => "Success",
                "data" => [
                    "users" => $users,
                    "pagination" => $pagination
                ]

            ];
            $this->redis->set($cacheKey, serialize($response));
        } else {
            $response = unserialize($response);
        }
        $this->set(['status_code', 'status_message', 'data'], $response);
        $this->viewBuilder()->setOption('serialize', ['status_code', 'status_message', 'data']);
    }

    public function view($id)
    {
        $cacheKey = 'users::' . $id;
        $response = $this->redis->get($cacheKey);
        if (!$response) {
            $user = $this->Users->get($id);
            $response = [
                "status_code" => "cdc-200",
                "status_message" => "Success",
                "data" => $user
            ];
            $this->redis->set($cacheKey, serialize($response));
        } else {
            $response = unserialize($response);
        }
        $this->set(['status_code', 'status_message', 'data'], $response);
        $this->viewBuilder()->setOption('serialize', ['status_code', 'status_message', 'data']);
    }

    public function add()
    {
        $this->request->allowMethod(['post', 'put']);
        $user = $this->Users->newEntity($this->request->getData());
        if ($this->Users->save($user)) {
            $message = 'Saved';
            $status_code = 'cdc-200';

            $caches = $this->redis->keys('users::list*');
            foreach ($caches as $c) {
                $this->redis->del($c);
            }
        } else {
            $message = 'Error';
            $status_code = 'cdc-115';
        }
        $this->set([
            'status_code' => $status_code,
            'status_message' => $message,
            'data' => $user,
        ]);
        $this->response = $this->response->withStatus(201);
        $this->viewBuilder()->setOption('serialize', ['data', 'status_code', 'status_message']);
    }

    public function edit($id)
    {
        $this->request->allowMethod(['patch', 'post', 'put']);
        $user = $this->Users->get($id);
        $user = $this->Users->patchEntity($user, $this->request->getData());
        if ($this->Users->save($user)) {
            $message = 'Saved';
            $status_code = 'cdc-200';

            $caches = $this->redis->keys('users::list*');
            foreach ($caches as $c) {
                $this->redis->del($c);
            }
            $this->redis->del('users::' . $id);
        } else {
            $message = 'Error';
            $status_code = 'cdc-115';
        }
        $this->set([
            'status_code' => $status_code,
            'status_message' => $message,
            'data' => $user,
        ]);
        $this->viewBuilder()->setOption('serialize', ['status_code', 'status_message', 'data']);
    }

    public function delete($id)
    {
        $this->request->allowMethod(['delete']);
        $user = $this->Users->get($id);

        if ($this->Users->delete($user)) {
            $caches = $this->redis->keys('users::list*');
            foreach ($caches as $c) {
                $this->redis->del($c);
            }
            $this->redis->del('users::' . $id);
        }
        $this->set([
            'status_code' => null,
            'status_message' => null,
            'data' => null
        ]);
        $this->response = $this->response->withStatus(204);
        $this->viewBuilder()->setOption('serialize', ['status_code', 'status_message', 'data']);
    }
}
