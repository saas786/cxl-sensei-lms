/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import ExtensionActions, { getExtensionActions } from './extension-actions';
import { EXTENSIONS_STORE } from './store';

/**
 * Extensions card component.
 *
 * @param {Object}   props               Component props.
 * @param {string}   props.title         Card title (extension title will be used as fallback).
 * @param {string}   props.excerpt       Card excerpt (extension excerpt will be used as fallback).
 * @param {string}   props.badgeLabel    Badge label (will check extension update if it's not defined).
 * @param {string}   props.image         Card image.
 * @param {Object}   props.htmlProps     Wrapper extra props.
 * @param {Object[]} props.customActions Array with custom actions containing the link props.
 */
const Card = ( props ) => {
	const {
		title,
		excerpt,
		badgeLabel,
		htmlProps,
		customActions,
		image,
	} = props;

	const componentInProgress = useSelect( ( select ) =>
		select( EXTENSIONS_STORE ).getComponentInProgress()
	);

	const actions =
		customActions || getExtensionActions( props, componentInProgress );

	const backgroundImage = image && `url(${ image })`;
	return (
		<article
			{ ...htmlProps }
			className={ classnames(
				'sensei-extensions__card',
				htmlProps?.className
			) }
		>
			<div
				className="sensei-extensions__card__image"
				style={ {
					backgroundImage,
				} }
			/>
			<div className="sensei-extensions__card__content">
				<header className="sensei-extensions__card__header">
					<h3 className="sensei-extensions__card__title">
						{ title }
					</h3>
					{ ( badgeLabel || props?.[ 'has_update' ] ) && (
						<small className="sensei-extensions__card__new-badge">
							{ badgeLabel || __( 'New version', 'sensei-lms' ) }
						</small>
					) }
				</header>
				<div className="sensei-extensions__card__body">
					<p className="sensei-extensions__card__description">
						{ excerpt }
					</p>
					{ actions && <ExtensionActions actions={ actions } /> }
				</div>
			</div>
		</article>
	);
};

export default Card;
