<?php namespace App;

use Illuminate\Database\Eloquent\Model;

class Message extends Model {

	/**
	 * The relationships that should be touched on save.
	 *
	 * @var array
	 */
	protected $touches = ['thread'];

	/**
	 * The attributes that can be set with Mass Assignment.
	 *
	 * @var array
	 */
	protected $fillable = ['thread_id', 'user_id', 'body'];

	/**
	 * Validation rules.
	 *
	 * @var array
	 */
	protected $rules = [
		'body' => 'required',
	];

	/**
	 * Thread relationship
	 *
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
	 */
	public function thread()
	{
		return $this->belongsTo('App\Thread');
	}

	/**
	 * User relationship
	 *
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
	 */
	public function user()
	{
		return $this->belongsTo('App\User')->withTrashed();
	}

	/**
	 * Participants relationship
	 *
	 * @return \Illuminate\Database\Eloquent\Relations\HasMany
	 */
	public function participants()
	{
		return $this->hasMany('App\Participant', 'thread_id', 'thread_id');
	}

	/**
	 * Recipients of this message
	 *
	 * @return \Illuminate\Database\Eloquent\Relations\HasMany
	 */
	public function recipients()
	{
		return $this->participants()->where('user_id', '!=', $this->user_id)->get();
	}

}
