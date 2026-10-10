<?php
/**
 * Dados linguísticos (português e inglês) usados pelas análises.
 *
 * @package SeoLegibilidade
 */

namespace SeoLegibilidade\Analysis;

defined( 'ABSPATH' ) || exit;

class Language {

	/**
	 * Converte um locale do WordPress (pt_BR, en_US…) no idioma suportado.
	 */
	public static function from_locale( $locale ) {
		return 0 === strpos( strtolower( (string) $locale ), 'en' ) ? 'en' : 'pt';
	}

	/**
	 * Fórmula de Flesch Reading Ease.
	 * Português: adaptação de Martins et al. (1996), 248,835 − 1,015·ASL − 84,6·ASW.
	 * Inglês: fórmula original de Flesch (1948), 206,835 − 1,015·ASL − 84,6·ASW.
	 */
	public static function flesch( $lang, $words_per_sentence, $syllables_per_word ) {
		$base  = 'pt' === $lang ? 248.835 : 206.835;
		$score = $base - 1.015 * $words_per_sentence - 84.6 * $syllables_per_word;
		return max( 0, min( 100, round( $score, 1 ) ) );
	}

	/**
	 * @return string[]
	 */
	public static function stopwords( $lang ) {
		if ( 'en' === $lang ) {
			return array( 'a', 'an', 'the', 'and', 'or', 'but', 'of', 'to', 'in', 'on', 'at', 'for', 'with', 'by', 'from', 'is', 'are', 'was', 'were', 'be', 'it', 'its', 'this', 'that', 'these', 'those', 'as', 'how', 'what', 'why', 'when', 'your', 'you', 'my', 'our', 'their', 'do', 'does', 'can', 'will', 'into', 'about', 'than', 'then' );
		}
		return array( 'a', 'o', 'as', 'os', 'um', 'uma', 'uns', 'umas', 'de', 'do', 'da', 'dos', 'das', 'em', 'no', 'na', 'nos', 'nas', 'ao', 'aos', 'à', 'às', 'por', 'pelo', 'pela', 'pelos', 'pelas', 'para', 'pra', 'com', 'sem', 'e', 'ou', 'mas', 'que', 'se', 'é', 'são', 'foi', 'ser', 'seu', 'sua', 'seus', 'suas', 'meu', 'minha', 'este', 'esta', 'esse', 'essa', 'isso', 'isto', 'aquele', 'aquela', 'num', 'numa', 'dum', 'duma', 'como', 'qual', 'quais', 'quando', 'onde', 'mais', 'muito', 'já', 'também', 'entre', 'sobre', 'até', 'lhe', 'ele', 'ela', 'eles', 'elas', 'você', 'vocês' );
	}

	/**
	 * Palavras e expressões de transição (conectivos).
	 *
	 * @return string[]
	 */
	public static function transition_words( $lang ) {
		if ( 'en' === $lang ) {
			return array( 'accordingly', 'additionally', 'afterward', 'afterwards', 'also', 'although', 'besides', 'because', 'consequently', 'conversely', 'finally', 'first', 'firstly', 'furthermore', 'hence', 'however', 'indeed', 'instead', 'likewise', 'meanwhile', 'moreover', 'nevertheless', 'nonetheless', 'notably', 'otherwise', 'second', 'secondly', 'similarly', 'specifically', 'still', 'subsequently', 'then', 'therefore', 'thus', 'ultimately', 'whereas', 'while', 'yet', 'above all', 'after all', 'as a result', 'as well as', 'at the same time', 'by contrast', 'even though', 'for example', 'for instance', 'for this reason', 'in addition', 'in conclusion', 'in contrast', 'in fact', 'in other words', 'in short', 'in summary', 'on the other hand', 'so that', 'such as', 'that is', 'to sum up', 'in the meantime', 'as soon as', 'in particular', 'in order to', 'due to', 'rather than' );
		}
		return array(
			// Simples.
			'além', 'ademais', 'outrossim', 'também', 'igualmente', 'inclusive', 'aliás', 'portanto', 'logo', 'assim', 'consequentemente', 'conseguinte', 'então', 'porém', 'contudo', 'todavia', 'entretanto', 'mas', 'embora', 'conquanto', 'enquanto', 'porque', 'pois', 'porquanto', 'caso', 'primeiramente', 'inicialmente', 'posteriormente', 'finalmente', 'depois', 'antes', 'enfim', 'afinal', 'sobretudo', 'principalmente', 'especialmente', 'certamente', 'evidentemente', 'obviamente', 'decerto', 'realmente', 'efetivamente', 'resumidamente', 'concluindo', 'similarmente', 'analogamente', 'paralelamente', 'simultaneamente', 'eventualmente', 'ocasionalmente', 'frequentemente', 'raramente', 'exceto', 'salvo', 'senão', 'ainda', 'quer', 'mesmo',
			// Locuções.
			'além disso', 'além do mais', 'por isso', 'por conseguinte', 'por consequência', 'sendo assim', 'desse modo', 'deste modo', 'dessa forma', 'desta forma', 'dessa maneira', 'desta maneira', 'de modo que', 'de forma que', 'de maneira que', 'no entanto', 'não obstante', 'por outro lado', 'em contrapartida', 'ao contrário', 'apesar de', 'apesar disso', 'mesmo que', 'ainda que', 'se bem que', 'visto que', 'uma vez que', 'já que', 'dado que', 'posto que', 'tendo em vista', 'em virtude de', 'por causa de', 'devido a', 'graças a', 'por exemplo', 'isto é', 'ou seja', 'a saber', 'em outras palavras', 'quer dizer', 'em primeiro lugar', 'em segundo lugar', 'em terceiro lugar', 'por último', 'por fim', 'em seguida', 'logo após', 'logo depois', 'antes de tudo', 'acima de tudo', 'antes de mais nada', 'em suma', 'em resumo', 'em síntese', 'em conclusão', 'para concluir', 'de fato', 'com efeito', 'na verdade', 'sem dúvida', 'da mesma forma', 'do mesmo modo', 'da mesma maneira', 'nesse sentido', 'neste sentido', 'à medida que', 'à proporção que', 'a fim de', 'para que', 'contanto que', 'desde que', 'a menos que', 'a não ser que', 'em geral', 'de modo geral', 'em particular', 'ao mesmo tempo', 'enquanto isso', 'nesse meio tempo', 'em contraste', 'diferentemente', 'como resultado', 'em consequência', 'com isso', 'diante disso', 'por sua vez', 'bem como', 'não só', 'mas também', 'tanto quanto', 'assim como', 'assim que', 'logo que', 'sempre que', 'até que',
		);
	}

	/**
	 * Formas de "ser" (e auxiliares equivalentes) que formam a voz passiva analítica.
	 */
	public static function passive_auxiliaries( $lang ) {
		if ( 'en' === $lang ) {
			return array( 'am', 'is', 'are', 'was', 'were', 'be', 'been', 'being', "isn't", "aren't", "wasn't", "weren't", 'get', 'gets', 'got', 'gotten' );
		}
		return array( 'é', 'são', 'sou', 'somos', 'foi', 'foram', 'fui', 'fomos', 'era', 'eram', 'éramos', 'será', 'serão', 'serei', 'seremos', 'seria', 'seriam', 'seríamos', 'ser', 'serem', 'sido', 'sendo', 'seja', 'sejam', 'sejamos', 'fosse', 'fossem', 'fôssemos', 'for', 'forem', 'formos', 'fora', 'foras' );
	}

	/**
	 * Particípios irregulares comuns.
	 */
	public static function irregular_participles( $lang ) {
		if ( 'en' === $lang ) {
			return array( 'arisen', 'awoken', 'been', 'beaten', 'become', 'begun', 'bent', 'bet', 'bitten', 'blown', 'broken', 'brought', 'built', 'bought', 'caught', 'chosen', 'come', 'cost', 'cut', 'dealt', 'done', 'drawn', 'driven', 'eaten', 'fallen', 'fed', 'felt', 'fought', 'found', 'forgotten', 'forgiven', 'frozen', 'given', 'gone', 'grown', 'hung', 'heard', 'hidden', 'hit', 'held', 'hurt', 'kept', 'known', 'laid', 'led', 'left', 'lent', 'let', 'lost', 'made', 'meant', 'met', 'paid', 'put', 'read', 'ridden', 'rung', 'risen', 'run', 'said', 'seen', 'sold', 'sent', 'set', 'shaken', 'shot', 'shown', 'shut', 'sung', 'sunk', 'sat', 'slept', 'spoken', 'spent', 'spread', 'stolen', 'stuck', 'struck', 'sworn', 'swept', 'swum', 'taken', 'taught', 'torn', 'told', 'thought', 'thrown', 'understood', 'woken', 'worn', 'won', 'written' );
		}
		return array( 'feito', 'feita', 'feitos', 'feitas', 'dito', 'dita', 'ditos', 'ditas', 'escrito', 'escrita', 'escritos', 'escritas', 'visto', 'vista', 'vistos', 'vistas', 'posto', 'posta', 'postos', 'postas', 'aberto', 'aberta', 'abertos', 'abertas', 'coberto', 'coberta', 'cobertos', 'cobertas', 'descoberto', 'descoberta', 'descobertos', 'descobertas', 'entregue', 'entregues', 'aceito', 'aceita', 'aceitos', 'aceitas', 'eleito', 'eleita', 'eleitos', 'eleitas', 'preso', 'presa', 'presos', 'presas', 'morto', 'morta', 'mortos', 'mortas', 'pago', 'paga', 'pagos', 'pagas', 'ganho', 'ganhos', 'gasto', 'gasta', 'gastos', 'gastas', 'impresso', 'impressa', 'impressos', 'impressas', 'suspenso', 'suspensa', 'expresso', 'expressa', 'expulso', 'expulsa', 'extinto', 'extinta', 'feito', 'composto', 'composta', 'proposto', 'proposta', 'imposto', 'imposta', 'exposto', 'exposta', 'disposto', 'disposta', 'suposto', 'suposta', 'previsto', 'prevista', 'revisto', 'revista', 'desfeito', 'desfeita', 'satisfeito', 'satisfeita', 'refeito', 'refeita', 'eleito', 'corrompido', 'benzido', 'acendido', 'aceso', 'acesa', 'submerso', 'submersa', 'incluso', 'inclusa', 'salvo', 'salva', 'solto', 'solta', 'envolto', 'envolta', 'absorto', 'frito', 'frita', 'limpo', 'limpa', 'vindo', 'vinda' );
	}

	/**
	 * Palavras terminadas em -ado/-ido que normalmente não são particípios.
	 */
	public static function participle_exceptions( $lang ) {
		if ( 'en' === $lang ) {
			return array( 'need', 'speed', 'seed', 'feed', 'bed', 'red', 'shed', 'bred', 'hundred', 'sacred', 'naked', 'wicked', 'indeed', 'tired' );
		}
		return array( 'lado', 'lados', 'estado', 'estados', 'mercado', 'mercados', 'resultado', 'resultados', 'cuidado', 'cuidados', 'passado', 'significado', 'significados', 'conteúdo', 'conteúdos', 'sentido', 'sentidos', 'partido', 'partidos', 'vestido', 'vestidos', 'ruído', 'ruídos', 'marido', 'maridos', 'pedido', 'pedidos', 'apelido', 'apelidos', 'sabido', 'dado', 'dados', 'fado', 'gado', 'prado', 'soldado', 'soldados', 'advogado', 'advogados', 'delegado', 'delegados', 'deputado', 'deputados', 'aliado', 'aliados', 'feriado', 'feriados', 'bocado', 'pecado', 'pecados', 'recado', 'recados', 'senado', 'teclado', 'teclados', 'telhado', 'machado', 'sábado', 'sábados', 'agrado', 'grado', 'obrigado', 'obrigada', 'cansado', 'cansada', 'animado', 'animada', 'adequado', 'adequada', 'adequados', 'adequadas', 'apropriado', 'apropriada', 'necessitado', 'rápido', 'rápida', 'válido', 'válida', 'sólido', 'sólida', 'líquido', 'líquida', 'tímido', 'tímida', 'úmido', 'úmida', 'ávido', 'cândido', 'nítido', 'nítida', 'pálido', 'pálida', 'lúcido', 'lúcida', 'ácido', 'ácida', 'híbrido', 'híbrida', 'cálido', 'esplêndido', 'esplêndida', 'estúpido', 'unido', 'unidos', 'unida', 'unidas', 'querido', 'querida', 'conhecido', 'conhecida', 'divertido', 'divertida' );
	}

	/**
	 * Termos de link genéricos que prejudicam SEO e acessibilidade.
	 */
	public static function generic_anchors( $lang ) {
		return array( 'clique aqui', 'clique', 'aqui', 'saiba mais', 'leia mais', 'veja mais', 'acesse', 'link', 'este link', 'neste link', 'mais', 'click here', 'here', 'read more', 'learn more', 'more', 'this link', 'link here' );
	}

	/**
	 * Palavras que iniciam perguntas (subtítulos em forma de pergunta).
	 */
	public static function question_words( $lang ) {
		if ( 'en' === $lang ) {
			return array( 'how', 'what', 'why', 'when', 'which', 'where', 'who', 'whom', 'whose', 'is', 'are', 'can', 'should', 'does', 'do', 'will' );
		}
		return array( 'como', 'o que', 'que', 'qual', 'quais', 'quanto', 'quanta', 'quantos', 'quantas', 'quando', 'onde', 'por que', 'porque', 'quem', 'vale a pena', 'é possível', 'existe', 'pode', 'devo', 'preciso' );
	}

	/**
	 * Expressões de tempo relativo que envelhecem mal sem uma data concreta.
	 */
	public static function relative_time( $lang ) {
		if ( 'en' === $lang ) {
			return array( 'currently', 'nowadays', 'recently', 'this year', 'last year', 'next year', 'in recent years', 'last month', 'last week', 'these days', 'right now' );
		}
		return array( 'atualmente', 'hoje em dia', 'recentemente', 'ultimamente', 'este ano', 'neste ano', 'esse ano', 'nesse ano', 'ano passado', 'no ano que vem', 'próximo ano', 'nos últimos anos', 'mês passado', 'semana passada', 'nos dias de hoje' );
	}

	/**
	 * Expressões que indicam experiência própria (o primeiro "E" de E-E-A-T).
	 */
	public static function experience_phrases( $lang ) {
		if ( 'en' === $lang ) {
			return array( 'we tested', 'i tested', 'in my experience', 'in our experience', 'our tests', 'my tests', 'we used', 'i used', 'hands-on', 'we compared', 'i compared', 'we reviewed', 'we visited', 'we interviewed', 'i have been', 'we have been' );
		}
		return array( 'testamos', 'testei', 'experimentamos', 'experimentei', 'na minha experiência', 'na nossa experiência', 'em nossos testes', 'nos nossos testes', 'em meus testes', 'nos meus testes', 'usei', 'usamos', 'utilizei', 'utilizamos', 'na prática', 'avaliamos', 'avaliei', 'comparamos', 'comparei', 'visitamos', 'visitei', 'entrevistamos', 'entrevistei', 'eu uso', 'nós usamos', 'trabalho com', 'trabalhamos com' );
	}
}
