<?php namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\SoftDeletes;

class Thread extends Eloquent {
	use SoftDeletes;
	/**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'threads';

	/**
	 * The attributes that can be set with Mass Assignment.
	 *
	 * @var array
	 */
	protected $fillable = ['subject'];

	/**
	 * The attributes that should be mutated to dates.
	 *
	 * @var array
	 */
	protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    /**
     * Participants Array
     *
     * Returns an array containing user's First Name, Last Name, and Token
     * @param $user
     * @return array|static[]
     */
    public function participantsArray($user = null)
    {
        $user = $user ?: \Auth::user()->id;

        $participantNames = \DB::table('users')
            ->join('participants', 'users.id', '=', 'participants.user_id')
            ->where('users.id', '!=', $user)
            ->where('participants.thread_id', $this->id)
            ->get(['first_name', 'last_name', 'user_token']);

        return $participantNames;
    }
    public function participantsTokens($user = null)
    {
        $user = $user ?: \Auth::user()->id;

        $tokens = [];

        $participants = \DB::table('users')
            ->join('participants', 'users.id', '=', 'participants.user_id')
            ->where('users.id', '!=', $user)
            ->where('participants.thread_id', $this->id)
            ->get(['user_token']);

        foreach ($participants as $participant)
        {
            $tokens[] = $participant->user_token;
        }

        return $tokens;
    }

	/**
	 * Messages relationship
	 *
	 * @return \Illuminate\Database\Eloquent\Relations\HasMany
	 */
	public function messages()
	{
		return $this->hasMany('App\Message');
	}

	/**
	 * Returns the latest message from a thread
	 *
	 * @return \App\Message
	 */
	public function latestMessage()
	{
		return $this->messages()->latest()->first();
	}

	/**
	 * Participants relationship
	 *
	 * @return \Illuminate\Database\Eloquent\Relations\HasMany
	 */
	public function participants()
	{
		return $this->hasMany('App\Participant');
	}

	/**
	 * Returns all of the latest threads by updated_at date
	 *
	 * @return mixed
	 */
	public static function getAllLatest()
	{
		return self::latest('updated_at')->get();
	}

	/**
	 * Returns an array of user ids that are associated with the thread
	 *
	 * @param null $userId
	 * @return array
	 */
	public function participantsUserIds($userId = null)
	{
		$users = $this->participants()->lists('user_id');

		if ($userId) {
			$users[] = $userId;
		}

		return $users;
	}

	/**
	 * Returns threads that the user is associated with
	 *
	 * @param $query
	 * @param $userId
	 * @return mixed
	 */
	public function scopeForUser($query, $userId)
	{
		return $query->join('participants', 'threads.id', '=', 'participants.thread_id')
			->where('participants.user_id', $userId)
			->where('participants.deleted_at', null)
			->select('threads.*')
			->latest('updated_at')
			->get();
	}

	/**
	 * Returns threads with new messages that the user is associated with
	 *
	 * @param $query
	 * @param $userId
	 * @return mixed
	 */
	public function scopeForUserWithNewMessages($query, $userId)
	{
		return $query->join('participants', 'threads.id', '=', 'participants.thread_id')
			->where('participants.user_id', $userId)
			->whereNull('participants.deleted_at')
			->where(function ($query) {
				$query->where('threads.updated_at', '>', $this->getConnection()->raw('participants.last_read'))
					->orWhereNull('participants.last_read');
			})
			->select('threads.*')
			->latest('updated_at')
			->get();
	}

	/**
	 * Adds users to this thread
	 *
	 * @param array $participants list of all participants
	 * @return void
	 */
	public function addParticipants(array $participants)
	{
		if (count($participants)) {
			foreach ($participants as $user_id) {
				Participant::firstOrCreate([
					'user_id' => $user_id,
					'thread_id' => $this->id,
				]);
			}
		}
	}

	/**
	 * Mark a thread as read for a user
	 *
	 * @param integer $userId
	 */
	public function markAsRead($userId)
	{
		try {
			$participant = $this->getParticipantFromUser($userId);
			$participant->last_read = new Carbon;
			$participant->save();
		} catch (ModelNotFoundException $e) {
			// do nothing
		}
	}

	/**
	 * See if the current thread is unread by the user
	 *
	 * @param integer $userId
	 * @return bool
	 */
	public function isUnread($userId)
	{
		try {
			$participant = $this->getParticipantFromUser($userId);
			if ($this->updated_at > $participant->last_read) {
				return true;
			}
		} catch (ModelNotFoundException $e) {
			// do nothing
		}

		return false;
	}

	/**
	 * Finds the participant record from a user id
	 *
	 * @param $userId
	 * @return mixed
	 * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
	 */
	public function getParticipantFromUser($userId)
	{
		return $this->participants()->where('user_id', $userId)->firstOrFail();
	}

	/**
	 * Restores all participants within a thread that has a new message
	 */
	public function activateAllParticipants()
	{
		$participants = $this->participants()->get();
		foreach ($participants as $participant) {
			$participant->restore();
		}
	}
}