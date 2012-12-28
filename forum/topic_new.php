<?php

require "../base.php";
access::no_guest();

new page_topic_new();
class page_topic_new
{
	/**
	 * Forumet
	 * @var forum
	 */
	protected $forum;
	
	/**
	 * Construct
	 */
	public function __construct()
	{
		$this->forum = new forum(getval("f"));
		$this->forum->require_access();
		$this->forum->add_title();
		ess::$b->page->add_title("Ny forumtr�d");
		
		$this->show();
		
		$this->forum->load_page();
	}
	
	/**
	 * Skjema for � opprette ny tr�d
	 */
	protected function show()
	{
		// kontroller rankkravet
		if (!$this->forum->check_rank())
		{
			// sett opp ranknavnet
			$rank_info = game::$ranks['items_number'][forum::TOPIC_MIN_RANK];
			
			echo '
<div class="bg1_c xsmall">
	<h1 class="bg1">Ny forumtr�d i '.htmlspecialchars($this->forum->get_name()).'<span class="left"></span><span class="right"></span></h1>
	<p class="h_left"><a href="forum?id='.$this->forum->id.'">&laquo; Tilbake</a></p>
	<div class="bg1">
		<div class="error_box" style="padding: 10px 0">
			<p>Du har for lav rank for � kunne opprette forumtr�der i forumet.</p>
			<p>For � kunne opprette en ny forumtr�d m� du ha n�dd ranken <b>'.htmlspecialchars($rank_info['name']).'</b>.</p>
			<p>Se ogs� <a href="'.ess::$s['relative_path'].'/node/5">hjelp</a>.</p>
			<p><a href="forum?id='.$this->forum->id.'">Tilbake</a></p>
		</div>
	</div>
</div>';
			
			return;
		}
		
		// kontroller blokkeringer
		$block = $this->forum->check_block();
		
		// opprette forumtr�den?
		if (!$block && isset($_POST['opprett']))
		{
			$title = postval("title");
			$text = postval("text");
			
			// type forumtr�d og l�st/ul�st
			$type = NULL;
			$locked = NULL;
			if ($this->forum->fmod)
			{
				$type = postval("type");
				$locked = isset($_POST['locked']);
			}
			
			// fors�k � opprett forumtr�den
			$this->forum->add_topic($title, $text, $type, $locked);
		}
		
		echo '
<div class="bg1_c forumw forumnewtopic">
	<h1 class="bg1">Ny forumtr�d i '.htmlspecialchars($this->forum->get_name()).'<span class="left"></span><span class="right"></span></h1>
	<p class="h_left"><a href="forum?id='.$this->forum->id.'">&laquo; Tilbake</a></p>
	<div class="bg1">
		<boxes />
		<div id="topic_info_add"></div>
		<div class="forum_reply_edit_c">
		<form action="" method="post">
			<dl class="dl_2x">
				<dt>Tittel</dt>
				<dd>
					<input type="text" name="title" id="topic_title" class="styled w300" value="'.htmlspecialchars(postval("title")).'" maxlength="40" />';
		
		if ($this->forum->fmod)
		{
			$type = intval(postval("type"));
			
			echo '
					<select name="type" id="topic_type">
						<option value="1"'.($type == 1 ? ' selected="selected"' : '').'>Normal forumtr�d</option>
						<option value="2"'.($type == 2 ? ' selected="selected"' : '').'>Sticky forumtr�d</option>
						<option value="3"'.($type == 3 ? ' selected="selected"' : '').'>Viktig forumtr�d</option>
					</select>
				</dd>
				<dt>L�st</dt>
				<dd><input type="checkbox" name="locked" id="topic_locked"'.(isset($_POST['locked']) ? ' checked="checked"' : '').' /><label for="topic_locked"> L�s forumtr�den for endringer</label>';
		}
		
		echo '</dd>
				<dt>Innhold</dt>
				<dd><textarea name="text" rows="20" cols="75" id="topic_text">'.htmlspecialchars(postval("text")).'</textarea></dd>
			</dl>
			<p class="c">Husk at <a href="'.ess::$s['relative_path'].'/node/6" target="_blank">forumreglene</a> til enhver tid skal f�lges.</p>
			<p class="c">
				'.show_sbutton("Opprett", 'name="opprett" accesskey="s" id="topic_add"').'
				'.show_sbutton("Forh�ndsvis", 'name="preview" accesskey="p" id="topic_preview"').'
			</p>
		</form>
		</div>
		<div id="topic_info">';
		
		// forh�ndsvisning
		if (isset($_POST['preview']))
		{
			$data = array("ft_text" => postval("text"));
			
			echo '
			<div class="forum">'.forum::template_topic_preview($data).'
			</div>';
		}
		
		echo '
		</div>
	</div>
</div>';
		
		// div javascript
		ess::$b->page->add_js_file(ess::$s['relative_path']."/js/forum.js");
		ess::$b->page->add_js_domready('
	new NewForumTopic('.$this->forum->id.');');
	}
}