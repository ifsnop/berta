<?php


class Template {
	
	protected $template;
	protected $values = array();
	
	public function __construct($template) {
		$this->template = $template;
	}
	
	public function set($key, $value) {
		$this->values[$key] = $value;
	}
	
	public function output() {
		$output = $this->template;
		foreach ($this->values as $key => $value) {
			$tagToReplace = "[@$key]";
			$output = str_replace($tagToReplace, $value, $output);
		}
		return preg_replace("/\[\@\w+\]/", "", $output);
		//return $output;
	}
	/**
	 * Merges the content from an array of templates and separates it with $separator.
	 *
	 * @param array $templates an array of Template objects to merge
	 * @param string $separator the string that is used between each Template object
	 * @return string
	 */
	static public function merge($templates, $separator = "\n") {
		/**
		 * Loops through the array concatenating the outputs from each template, separating with $separator.
		 * If a type different from Template is found we provide an error message.
		 */
		$output = "";

		foreach ($templates as $template) {
			$content = (get_class($template) !== "Template")
			? "Error, incorrect type - expected Template."
					: $template->output();
					$output .= $content . $separator;
		}
		return $output;
	}
}
