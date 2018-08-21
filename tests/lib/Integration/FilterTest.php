<?php
/**
 * Copyright 2018 Cloud Creativity Limited
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace CloudCreativity\LaravelJsonApi\Tests\Integration;

use DummyApp\Comment;
use DummyApp\Post;
use DummyApp\User;

class FilterTest extends TestCase
{

    /**
     * @var string
     */
    protected $resourceType;


    /**
     * The `id` filter must work with other filters. In this example, if
     * we filter for `id` plus `created-by` we are asking: *of these
     * comments, which were created by the specified user?*
     *
     * @see https://github.com/cloudcreativity/laravel-json-api/issues/219
     */
    public function testIdAsMultiple()
    {
        $user = factory(User::class)->create();
        $comments = factory(Comment::class, 2)->create([
            'user_id' => $user->getKey(),
        ]);

        $other = factory(Comment::class)->create();

        $filter = ['filter' => ['created-by' => $user->getRouteKey()]];

        $this->resourceType = 'comments';
        $this->actingAsUser()
            ->doSearchById([$comments[0], $comments[1], $other], $filter)
            ->assertSearchedIds($comments);
    }

    public function testIdWithPaging()
    {
        $comments = factory(Comment::class, 3)->create();

        $this->resourceType = 'comments';
        $this->actingAsUser()
            ->doSearchById($comments, ['page' => ['number' => 1, 'size' => 2]])
            ->assertSearchedIds([$comments[0], $comments[1]])
            ->assertJson([
                'meta' => [
                    'page' => [
                        'current-page' => 1,
                        'per-page' => 2,
                    ],
                ],
            ]);
    }

    public function testToManyId()
    {
        $post = factory(Post::class)->create();
        $comments = factory(Comment::class, 3)->create([
            'commentable_type' => Post::class,
            'commentable_id' => $post->getKey(),
        ]);

        $ids = $this->normalizeIds([$comments[0], $comments[2], '999']);

        $this->resourceType = 'posts';
        $this->actingAsUser()
            ->doReadRelated($post, 'comments', ['filter' => ['id' => $ids]])
            ->assertReadHasMany('comments', [$comments[0], $comments[2]]);
    }

    /**
     * Must be able to filter a read resource request.
     *
     * @see https://github.com/cloudcreativity/laravel-json-api/issues/218
     */
    public function testFilterResource()
    {
        $post = factory(Post::class)->states('published')->create();

        $expected = [
            'type' => 'posts',
            'id' => (string) $post->getRouteKey(),
            'attributes' => [
                'title' => $post->title,
            ],
        ];

        $this->resourceType = 'posts';
        $this->doRead($post, ['filter' => ['published' => 1]])
            ->assertSearchedOne($expected);
    }

    public function testFilterResourceDoesNotMatch()
    {
        $post = factory(Post::class)->create();
        factory(Post::class)->states('published')->create(); // should not appear as the result

        $this->resourceType = 'posts';
        $this->doRead($post, ['filter' => ['published' => 1]])->assertSearchedOne(null);
    }

    /**
     * The `id` filter must be rejected for a read resource request as it makes
     * no sense to include it because the URL is already scoped by id.
     */
    public function testFilterResourceRejectsIdFilter()
    {
        $post = factory(Post::class)->create();

        $this->resourceType = 'posts';
        $this->doRead($post, ['filter' => ['id' => '999']])
            ->assertStatus(400)
            ->assertJson([
                'errors' => [
                    [
                        'source' => ['parameter' => 'filter'],
                    ],
                ],
            ]);
    }

    public function testFilterToOne()
    {
        $comment = factory(Comment::class)->states('post')->create();

        $expected = [
            'type' => 'posts',
            'id' => (string) $comment->commentable->getRouteKey(),
            'attributes' => [
                'title' => $comment->commentable->title,
            ],
        ];

        $this->resourceType = 'comments';

        $this->actingAsUser()
            ->doReadRelated($comment, 'commentable', ['filter' => ['published' => 1]])
            ->assertReadHasOne($expected);
    }

    public function testFilterToOneDoesNotMatch()
    {
        $post = factory(Post::class)->create();
        $comment = factory(Comment::class)->create([
            'commentable_type' => Post::class,
            'commentable_id' => $post->getKey(),
        ]);

        factory(Comment::class)->states('post')->create();

        $this->resourceType = 'comments';

        $this->actingAsUser()
            ->doReadRelated($comment, 'commentable', ['filter' => ['published' => 1]])
            ->assertReadHasOne(null);
    }
}
