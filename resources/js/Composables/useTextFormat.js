import { startCase, toLower } from 'lodash';

export function useTextFormat() {
    const toTitleCase = (text) => {
        if (!text || typeof text !== 'string') return text;
        
        if (text !== text.toUpperCase()) {
            return text;
        }
        
        return startCase(toLower(text));
    };

    return {
        toTitleCase
    };
}
