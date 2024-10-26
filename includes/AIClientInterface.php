<?php

/**
 * Interface for AI client classes. Usage:
 *
 * $openAIClient = new OpenAIClient();
 * $otherAIClient = new OtherAIClient(); // Assuming you create this in the future
 *
 * function useAIClient(AIClientInterface $client, string $message) {
 *      $response = $client->generateText($message);
 *      echo $response;
 * }
 *
 * useAIClient($openAIClient, "Hello, AI!");
 * useAIClient($otherAIClient, "Hello, Other AI!");
 */

 interface AIClientInterface {
     /**
     * Sets the system message for the AI model.
     *
     * @param string $message The system message to set.
     * @return void
     */
    public function setSystemMessage(string $message): void;

    /**
     * Generates text based on the user's message.
     *
     * @param string $userMessage The user's input message.
     * @return string The generated text response.
     */
    public function generateText(string $userMessage): string;

    /**
     * Generates a JSON response based on the user's message.
     *
     * @param string $userMessage The user's input message.
     * @return array The generated JSON data as an associative array.
     */
    public function generateJson(string $userMessage): array;

    /**
     * Sends a chat message and receives a text response.
     *
     * @param string $userMessage The user's chat message.
     * @return string The AI's text response.
     */
    public function chat(string $userMessage): string;

    /**
     * Sends a chat message and receives a JSON response.
     *
     * @param string $userMessage The user's chat message.
     * @return array The AI's JSON response as an associative array.
     */
    public function json(string $userMessage): array;
}