// @ts-check

/**
 * External dependencies
 */
import * as React from 'react';

/**
 * WordPress dependencies
 */
import {
	EditorProvider,
	ErrorBoundary,
	PostSavedState,
	PostTitle,
	PostPublishButton,
} from '@wordpress/editor';
import { withSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { Fields } from './';

/**
 * The migration admin page.
 *
 * @param {Object} props The component props.
 * @return {React.ReactElement} The main editor component.
 */
const Editor = ( props ) => {
	const { onError, post, settings, initialEdits } = props;

	if ( ! post ) {
		return null;
	}

	return (
		<EditorProvider
			settings={
				{
					...settings,
					richEditingEnabled: false,
				}
			}
			post={ post }
			initialEdits={ initialEdits }
			useSubRegistry={ false }
		>
			<ErrorBoundary onError={ onError }>
				<PostTitle />
				<PostSavedState />
				<PostPublishButton />
				<Fields />
			</ErrorBoundary>
		</EditorProvider>
	);
};

export default withSelect( ( select, { postId, postType } ) => {
	const { getEntityRecord } = select( 'core' );

	return {
		post: getEntityRecord( 'postType', postType, postId ),
	};
} )( Editor );
