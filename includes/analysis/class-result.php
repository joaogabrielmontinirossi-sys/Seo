<?php
/**
 * Resultado de uma verificação individual e agregação em nota.
 *
 * @package SeoLegibilidade
 */

namespace SeoLegibilidade\Analysis;

defined( 'ABSPATH' ) || exit;

class Result {

	const GOOD = 'good';
	const OK   = 'ok';
	const BAD  = 'bad';

	/**
	 * Pontos de cada classificação (escala de 0 a 9, como nas ferramentas de mercado).
	 */
	const POINTS = array(
		self::GOOD => 9,
		self::OK   => 6,
		self::BAD  => 3,
	);

	/** @var string */
	public $id;
	/** @var string */
	public $label;
	/** @var string */
	public $rating;
	/** @var string */
	public $message;
	/** @var string|null Identificador de correção automática disponível. */
	public $fix;

	public function __construct( $id, $label, $rating, $message, $fix = null ) {
		$this->id      = $id;
		$this->label   = $label;
		$this->rating  = $rating;
		$this->message = $message;
		$this->fix     = $fix;
	}

	public function to_array() {
		return array(
			'id'      => $this->id,
			'label'   => $this->label,
			'rating'  => $this->rating,
			'message' => $this->message,
			'fix'     => $this->fix,
		);
	}

	/**
	 * Nota de 0 a 100 a partir de uma lista de resultados.
	 *
	 * @param Result[] $results
	 */
	public static function score( array $results ) {
		if ( ! $results ) {
			return 0;
		}
		$sum = 0;
		foreach ( $results as $r ) {
			$sum += self::POINTS[ $r->rating ];
		}
		return (int) round( $sum / ( 9 * count( $results ) ) * 100 );
	}

	/**
	 * Classificação geral a partir de uma nota de 0 a 100.
	 */
	public static function rating_for_score( $score ) {
		if ( $score >= 71 ) {
			return self::GOOD;
		}
		return $score >= 41 ? self::OK : self::BAD;
	}

	/**
	 * Ordena: problemas primeiro, depois melhorias, depois aprovados.
	 *
	 * @param Result[] $results
	 * @return Result[]
	 */
	public static function sort( array $results ) {
		$order = array(
			self::BAD  => 0,
			self::OK   => 1,
			self::GOOD => 2,
		);
		usort(
			$results,
			static function ( $a, $b ) use ( $order ) {
				return $order[ $a->rating ] - $order[ $b->rating ];
			}
		);
		return $results;
	}
}
