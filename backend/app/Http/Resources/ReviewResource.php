<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Review */
class ReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'restaurant_id' => $this->restaurant_id,
            'title' => $this->title,
            'body' => $this->body,
            'ratings' => [
                'food' => $this->rating_food,
                'service' => $this->rating_service,
                'location' => $this->rating_location,
                'price' => $this->rating_price,
                'overall' => $this->rating_overall,
                'label' => $this->score_label,
            ],
            'status' => $this->status->value,
            'votes' => [
                'helpful' => $this->helpful_count,
                'unhelpful' => $this->unhelpful_count,
                /*
                 * The viewer's own vote: true = helpful, false = not helpful,
                 * null = has not voted. Only present when the controller
                 * resolved it for a signed-in user.
                 */
                'mine' => $this->when(
                    array_key_exists('my_vote', $this->getAttributes()),
                    fn () => $this->getAttributes()['my_vote']
                ),
            ],
            'author' => new UserResource($this->whenLoaded('user')),
            'restaurant' => new RestaurantCardResource($this->whenLoaded('restaurant')),
            'reply' => new ReviewReplyResource($this->whenLoaded('reply')),
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
