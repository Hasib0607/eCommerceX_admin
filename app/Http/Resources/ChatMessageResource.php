<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ChatMessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'sender_type' => $this->sender_type,
            'sender_id' => $this->sender_id,
            'content' => $this->content,
            'file_url' => $this->imageURL($this->file_url),
            'message_type' => $this->message_type,
            'file_type' => $this->file_type,
            'seen_status' => $this->seen_status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }


    /**
     * Return message file url
     *
     * @param $files
     * @return array
     */
    public function imageURL($files)
    {
        if (!empty($files)) {
            $files = explode(',', $files);

            $fileURL = [];
            if (count($files)) {
                foreach ($files as $file) {
                    $fileURL[] = asset('/chat/chatFile/' . $file);
                }
            }

            return $fileURL;
        }

        return $files;
    }

}
