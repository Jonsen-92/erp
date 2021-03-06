<?php

declare(strict_types=1);

namespace App\Controller;


use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Cache\Cache;

class GroupsController extends AppController
{
    public function index()
    {
        $page = $this->request->getQuery('page', 1);
        $limit = $this->request->getQuery('limit', 10);
        $order = $this->request->getQuery('order', 'id');
        $sort = $this->request->getQuery('sort', 'asc');
        $keyword = $this->request->getQuery('keyword');

        $cacheKey = "groups::list::" . serialize([
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

            $groups = $this->Groups->find()->order([$order => $sort]);
            if ($keyword) {
                $groups = $groups->where(['name LIKE ' => "%$keyword%"]);
            }
            $count = $groups->count();
            if ($limit > $count) {
                $limit = $count;
            }
            if (($page * $limit) > $count) {
                $page = intval(ceil($count / $limit));
            }
            $groups = $groups->limit($limit)->page($page)->all();

            if (empty($groups->toArray())) {
                throw new RecordNotFoundException(__('Group Not Found'));
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
                    "groups" => $groups,
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
        $cacheKey = 'groups::' . $id;
        $response = $this->redis->get($cacheKey);
        if (!$response) {
            $group = $this->Groups->get($id);
            $response = [
                "status_code" => "cdc-200",
                "status_message" => "Success",
                "data" => $group
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
        /*
        $config = \Kafka\ProducerConfig::getInstance();
        $config->setMetadataRefreshIntervalMs(10000);
        $config->setMetadataBrokerList('kafka:9092');
        $config->setBrokerVersion('1.0.0');
        $config->setRequiredAck(1);
        $config->setIsAsyn(false);
        $config->setProduceInterval(500);

        $producer = new \Kafka\Producer();
        $producer->send([
            ['topic' => 'add_group', 
             'value' => serialize($this->request->getData()), 
             'key' => 'testkey']
           ]
        );
        */
        $key = md5(rand() . date('YYYY-mm-dd H:i:s'));

        $producer = new \RdKafka\Producer(new \RdKafka\Conf());
        $producer->addBrokers("kafka:9092");

        $producerTopic = $producer->newTopic("add_group");
        $producerTopic->produce(RD_KAFKA_PARTITION_UA, 0, serialize(['key'=>$key, 'data'=>$this->request->getData()]));
        $producer->flush(500);

        $conf = new \RdKafka\Conf();
        $conf->set('group.id', 'resAddGroup');
        $consumer = new \RdKafka\Consumer($conf);
        $consumer->addBrokers("kafka");
        $topic = $consumer->newTopic("res_add_group");
        $topic->consumeStart(0, RD_KAFKA_OFFSET_BEGINNING);

        $response = [
            'status_code' => 'cdc-200',
            'status_message' => 'sudah dikirim ke kafka',
            'data' => null
        ];

        $looping = true;
        while($looping){
            $msg = $topic->consume(0, 1000);
            if(null === $msg || $msg->err === RD_KAFKA_RESP_ERR__PARTITION_EOF){
                continue;
            }
            elseif($msg->err){
                $response['status_code'] = 'cdc-100';
                $response['status_message'] = $msg->errstr();
                $looping = false;
                break;
            }else{
                $responseKafka = unserialize($msg->payload);
                if(isset($responseKafka["key"]) && $responseKafka["key"]=== $key) {
                    $looping = false;
                    $response = $responseKafka["response"];

                    if($response["status_code"] === "cdc-200"){
                        $this->response = $this->response->withStatus(201);
                    }
                    else{
                        $this->response = $this->response->withStatus(400);
                    }
                }
            }
        }
        $this->set($response);
        $this->viewBuilder()->setOption('serialize', ['status_code', 'status_message','data']);
    }

    public function edit($id)
    {
        $this->request->allowMethod(['patch', 'post', 'put']);
        $group = $this->Groups->get($id);
        $group = $this->Groups->patchEntity($group, $this->request->getData());
        if ($this->Groups->save($group)) {
            $message = 'Saved';
            $status_code = 'cdc-200';

            $caches = $this->redis->keys('groups::list*');
            foreach ($caches as $c) {
                $this->redis->del($c);
            }
            $this->redis->del('groups::' . $id);
        } else {
            $message = 'Error';
            $status_code = 'cdc-115';
        }
        $this->set([
            'status_code' => $status_code,
            'status_message' => $message,
            'data' => $group,
        ]);
        $this->viewBuilder()->setOption('serialize', ['status_code', 'status_message', 'data']);
    }

    public function delete($id)
    {
        $this->request->allowMethod(['delete']);
        $group = $this->Groups->get($id);

        if ($this->Groups->delete($group)) {
            $message = null;
            $status_code = null;

            $caches = $this->redis->keys('groups::list*');
            foreach ($caches as $c) {
                $this->redis->del($c);
            }
            $this->redis->del('groups::' . $id);
        } else {
            $message = 'Error';
            $status_code = 'cdc-115';
        }

        $this->set([
            'status_code' => null,
            'status_message' => null,
            'data' => null,
        ]);
        $this->response = $this->response->withStatus(204);
        $this->viewBuilder()->setOption('serialize', ['status_code', 'status_message', 'data']);
    }
}
